# AWS デプロイ手順書

hiiragi ポートフォリオサイト(Next.js 16 + Laravel 13 API + SQLite)を AWS に公開するための手順。

## 0. 構成の方針

小規模なポートフォリオサイトなので、**EC2 1台に全部載せる構成**を推奨する。
月額をできるだけ抑えつつ、Laravel / Next.js / SQLite / 画像アップロードをそのまま動かせる。

```
[ブラウザ]
   │ https
   ▼
[Route 53] hiiragi.example.com / api.hiiragi.example.com
   │
   ▼
[EC2 1台(Amazon Linux 2023)]
   ├── Nginx(リバースプロキシ + HTTPS 終端)
   │     ├── hiiragi.example.com      → localhost:3000(Next.js)
   │     └── api.hiiragi.example.com  → PHP-FPM(Laravel)
   ├── Next.js(systemd で常駐)
   ├── Laravel 13 API(PHP 8.3 + PHP-FPM)
   │     ├── SQLite(database/database.sqlite)
   │     └── storage/app/public/works/(アップロード画像)
   └── cron → S3 へ SQLite + 画像を毎日バックアップ
```

### 代替構成との比較

| 構成 | 月額目安 | 特徴 |
|---|---|---|
| **EC2 1台(本手順)** | 約 $10〜15(t4g.small) | 最安・シンプル。SQLite のまま動く |
| Amplify Hosting(front)+ EC2(API) | 約 $12〜20 | フロントのビルド・配信を AWS に任せられる。管理対象が2つに増える |
| App Runner + RDS | 約 $40〜 | フルマネージドだがこの規模ではオーバースペック。SQLite → RDS 移行も必要 |

> フロントを Vercel(無料枠)に置き、AWS には API だけ置く構成も現実的な選択肢。
> その場合は本手順の「6. Next.js」「7. Nginx のフロント側設定」を読み飛ばせばよい。

---

## 1. 事前準備

1. **AWS アカウント作成**(既にあればスキップ)
   - ルートユーザーに MFA を設定する
2. **IAM ユーザー(または IAM Identity Center)作成**
   - 日常操作はルートユーザーではなく管理者権限の IAM ユーザーで行う
3. **ドメイン取得**
   - Route 53 で取得(`.com` 年 $14 前後)、または他社取得ドメインを Route 53 に向ける
4. **リージョン**: 東京(`ap-northeast-1`)を選択

---

## 2. EC2 インスタンス作成

マネジメントコンソール → EC2 → 「インスタンスを起動」。

| 項目 | 値 |
|---|---|
| AMI | Amazon Linux 2023(arm64) |
| インスタンスタイプ | `t4g.small`(2GB RAM。`t4g.micro` 1GB でも動くがビルド時に厳しい) |
| キーペア | 新規作成(`.pem` をローカルに保存) |
| ストレージ | gp3 20GB |

**セキュリティグループ**(ファイアウォール):

| タイプ | ポート | ソース |
|---|---|---|
| SSH | 22 | 自分のIPのみ(マイIP) |
| HTTP | 80 | 0.0.0.0/0 |
| HTTPS | 443 | 0.0.0.0/0 |

作成後、**Elastic IP を割り当てて関連付ける**(再起動でIPが変わるのを防ぐ)。

### Route 53 で DNS 設定

ホストゾーンに A レコードを2つ作成し、どちらも Elastic IP に向ける。

| レコード名 | タイプ | 値 |
|---|---|---|
| `hiiragi.example.com` | A | Elastic IP |
| `api.hiiragi.example.com` | A | Elastic IP |

---

## 3. サーバー初期設定

ローカルから SSH 接続:

```bash
ssh -i ~/.ssh/hiiragi.pem ec2-user@<Elastic IP>
```

必要なソフトをインストール:

```bash
sudo dnf update -y

# PHP 8.3 + 拡張(Laravel 13 に必要なもの)
sudo dnf install -y php8.3 php8.3-fpm php8.3-cli php8.3-mbstring \
  php8.3-xml php8.3-pdo php8.3-sqlite3 php8.3-gd php8.3-opcache php8.3-intl

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 22(Next.js 16 の要件を満たす LTS)
sudo dnf install -y nodejs22
sudo alternatives --set node /usr/bin/node-22

# Nginx / git / cron
sudo dnf install -y nginx git cronie
sudo systemctl enable --now nginx php-fpm crond
```

GitHub から clone するための **デプロイ用 SSH キー** をサーバー上で作成し、
公開鍵を GitHub リポジトリの Deploy keys(read-only)に登録する:

```bash
ssh-keygen -t ed25519 -C "hiiragi-ec2" -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub   # → GitHub の hiiragi リポジトリ Settings → Deploy keys に登録
```

---

## 4. Laravel API のデプロイ

```bash
sudo mkdir -p /var/www && sudo chown ec2-user:ec2-user /var/www
cd /var/www
git clone git@github.com:kawasoe-syouta/hiiragi.git
cd hiiragi/api

composer install --no-dev --optimize-autoloader
```

### .env(本番用)

```bash
cp .env.example .env
php artisan key:generate
```

`.env` を本番値に編集(重要な項目のみ抜粋):

```ini
APP_NAME=hiiragi
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.hiiragi.example.com

DB_CONNECTION=sqlite

# 管理API の固定トークン(openssl rand -hex 32 で生成)
ADMIN_API_TOKEN=<生成した値>
ADMIN_PASSWORD=<管理画面ログインパスワード>

# CORS: フロントのオリジンのみ許可
FRONTEND_URL=https://hiiragi.example.com

# メール(→ 手順 8 参照)
MAIL_MAILER=smtp
MAIL_TO_ADDRESS=<問い合わせの届け先アドレス>
```

### DB・ストレージ・キャッシュ

```bash
touch database/database.sqlite
php artisan migrate --force
php artisan db:seed --force        # サンプルデータが必要な場合のみ
php artisan storage:link

# 本番用キャッシュ
php artisan config:cache
php artisan route:cache

# パーミッション(PHP-FPM 実行ユーザーから書き込めるように)
sudo chown -R ec2-user:nginx storage bootstrap/cache database
sudo chmod -R 775 storage bootstrap/cache database
```

> SQLite は **DBファイル自体(`database/`)への書き込み権限**も必要な点に注意。

---

## 5. Next.js のデプロイ

> 現状フロントの実体は `hiiragi-portfolio-site/frontend/` にある。
> `app/frontend/` へ移した後は読み替えること。

```bash
cd /var/www
git clone git@github.com:kawasoe-syouta/hiiragi-portfolio-site.git
cd hiiragi-portfolio-site/frontend

# API の URL を本番向けに設定
echo 'NEXT_PUBLIC_API_URL=https://api.hiiragi.example.com' > .env.local

npm ci
npm run build
```

### systemd で常駐化

`/etc/systemd/system/hiiragi-frontend.service` を作成:

```ini
[Unit]
Description=hiiragi Next.js frontend
After=network.target

[Service]
User=ec2-user
WorkingDirectory=/var/www/hiiragi-portfolio-site/frontend
ExecStart=/usr/bin/npx next start -p 3000
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now hiiragi-frontend
curl -I http://localhost:3000   # 200 が返ればOK
```

---

## 6. Nginx 設定

`/etc/nginx/conf.d/hiiragi.conf` を作成:

```nginx
# フロントエンド(Next.js)
server {
    listen 80;
    server_name hiiragi.example.com;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

# API(Laravel)
server {
    listen 80;
    server_name api.hiiragi.example.com;
    root /var/www/hiiragi/api/public;
    index index.php;

    client_max_body_size 10m;   # 画像アップロード用

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

PHP-FPM の実行ユーザーを nginx に合わせる(`/etc/php-fpm.d/www.conf` の `user`/`group` を確認)後:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

この時点で `http://hiiragi.example.com` と `http://api.hiiragi.example.com/api/works` が表示されることを確認。

---

## 7. HTTPS 化(Let's Encrypt)

```bash
sudo dnf install -y certbot python3-certbot-nginx
sudo certbot --nginx -d hiiragi.example.com -d api.hiiragi.example.com
```

- Nginx 設定が自動で HTTPS 対応に書き換わり、HTTP → HTTPS リダイレクトも設定される
- 証明書の自動更新は certbot がタイマー登録する(`systemctl list-timers | grep certbot` で確認)

HTTPS 化後、`.env` の `APP_URL` / `FRONTEND_URL` と Next.js の `NEXT_PUBLIC_API_URL` が
`https://` になっていることを再確認し、変更した場合は:

```bash
cd /var/www/hiiragi/api && php artisan config:cache
cd /var/www/hiiragi-portfolio-site/frontend && npm run build && sudo systemctl restart hiiragi-frontend
```

---

## 8. メール送信(お問い合わせフォーム)

EC2 はスパム対策で SMTP(25番ポート)の送信が制限されているため、**587 ポートの外部 SMTP** を使う。

### 案A: Gmail SMTP(手軽・個人サイト向き)

Google アカウントで「アプリパスワード」を発行し `.env` に設定:

```ini
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=<Gmailアドレス>
MAIL_PASSWORD=<アプリパスワード>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=<Gmailアドレス>
```

### 案B: Amazon SES(送信量が増えたら)

1. SES コンソールで送信元ドメイン(またはメールアドレス)を検証
2. サンドボックス解除申請(本番送信に必須)
3. SMTP 認証情報を発行し、上記と同様に `.env` へ設定(`MAIL_HOST=email-smtp.ap-northeast-1.amazonaws.com`)

設定後 `php artisan config:cache` を忘れずに。お問い合わせフォームから実際に送って確認する。

---

## 9. バックアップ(S3)

SQLite とアップロード画像はこの EC2 にしか存在しないため、S3 への日次バックアップを設定する。

1. S3 バケット作成(例: `hiiragi-backup`、パブリックアクセスは全ブロック)
2. EC2 に IAM ロールを付与(対象バケットへの `s3:PutObject` / `s3:ListBucket` のみ許可)
3. cron に登録:

```bash
crontab -e
```

```cron
# 毎日 4:00 に SQLite と画像を S3 へ
0 4 * * * aws s3 cp /var/www/hiiragi/api/database/database.sqlite s3://hiiragi-backup/db/database-$(date +\%Y\%m\%d).sqlite && aws s3 sync /var/www/hiiragi/api/storage/app/public/works s3://hiiragi-backup/works/
```

S3 のライフサイクルルールで「90日より古い `db/` を削除」を設定しておくと容量が増え続けない。

---

## 10. 更新デプロイ(2回目以降)

### API 更新

```bash
cd /var/www/hiiragi/api
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
```

### フロント更新

```bash
cd /var/www/hiiragi-portfolio-site/frontend
git pull
npm ci && npm run build
sudo systemctl restart hiiragi-frontend
```

---

## 11. 公開前チェックリスト

- [ ] `APP_DEBUG=false` / `APP_ENV=production` になっている
- [ ] `ADMIN_API_TOKEN` / `ADMIN_PASSWORD` が本番用の強い値になっている
- [ ] `FRONTEND_URL` が本番ドメイン(CORS が正しく効いている)
- [ ] `https://` で全ページ表示できる(証明書エラーなし)
- [ ] `/admin` からログイン → 作品登録 → 画像アップロードが動く
- [ ] お問い合わせフォームからメールが届く
- [ ] S3 バックアップが実行されている(翌日確認)
- [ ] SSH のソースIPが「マイIP」に絞られている

## 費用目安(東京リージョン)

| 項目 | 月額目安 |
|---|---|
| EC2 t4g.small | 約 $12 |
| EBS gp3 20GB | 約 $1.6 |
| Elastic IP(インスタンスに関連付け中) | 無料 |
| Route 53 ホストゾーン | $0.50 |
| S3 バックアップ | $1 未満 |
| **合計** | **約 $15(2,300円前後)** |

※ 新規アカウントの無料利用枠(クレジット)が使える場合、初年度はさらに安くなる。
