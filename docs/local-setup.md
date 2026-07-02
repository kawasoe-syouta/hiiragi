# ローカル起動手順

セットアップ済みのこのマシンでサイトをローカル起動する手順。
(ゼロから環境構築する場合は [hiiragi-portfolio-site/SETUP.md](../hiiragi-portfolio-site/SETUP.md) を参照)

## 前提

以下はセットアップ済みのため、毎回の作業は不要。

- `api/` … Laravel 本体(`vendor/`・`.env`・`database.sqlite` あり)
- `hiiragi-portfolio-site/frontend/` … Next.js 本体(`node_modules/`・`.env.local` あり)
- PHP / Composer は Laravel Herd、Node.js はインストール済み

※ モノレポ側の `app/frontend/` は placeholder。動くフロントエンドは `hiiragi-portfolio-site/frontend/`。

## 起動(ターミナル2つ)

### ① バックエンド API(Laravel)

```bash
cd ~/Desktop/hiiragi/api
php artisan serve        # → http://localhost:8000
```

### ② フロントエンド(Next.js)

```bash
cd ~/Desktop/hiiragi/hiiragi-portfolio-site/frontend
npm run dev              # → http://localhost:3000
```

## 動作確認

| URL | 内容 |
|---|---|
| http://localhost:3000 | 公開サイト(作品は API から取得) |
| http://localhost:3000/admin | 管理画面(パスワードは `api/.env` の `ADMIN_PASSWORD`) |
| http://localhost:3000/contact | お問い合わせフォーム |

- ローカルは `MAIL_MAILER=log` のため、お問い合わせの送信内容は `api/storage/logs/laravel.log` に書き出される
- 実際にメールを届かせたい場合の SMTP 設定は [SETUP.md の「4. 本当にメールを届かせる」](../hiiragi-portfolio-site/SETUP.md) を参照

## トラブルシューティング

### アップロード画像が表示されない

`api/public/storage` のシンボリックリンク切れが原因のことが多い(プロジェクトフォルダを移動するとリンクが旧パスを指したままになる)。以下で張り直す:

```bash
cd ~/Desktop/hiiragi/api
rm public/storage
php artisan storage:link
```

※ 2026-07-03 に旧パス(`~/Desktop/hiiragi-api/`)を指していたリンク切れを上記手順で修正済み。

### `.env` を変更したのに反映されない

```bash
cd ~/Desktop/hiiragi/api
php artisan config:clear
```

### DB を初期状態(ダミー作品6件)に戻したい

```bash
cd ~/Desktop/hiiragi/api
php artisan migrate:fresh --seed
```

## 環境設定の対応関係

| 設定 | 場所 | 値 |
|---|---|---|
| API の URL | `api/.env` の `APP_URL` | `http://localhost:8000` |
| CORS 許可先 | `api/.env` の `FRONTEND_URL` | `http://localhost:3000` |
| フロントから見た API | `frontend/.env.local` の `NEXT_PUBLIC_API_URL` | `http://localhost:8000` |
