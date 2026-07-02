# Laravel API 仕様書(api/)

ポートフォリオサイトのバックエンド。Laravel 13(PHP 8.3+)の API 専用構成で、Blade による画面は持たない(`/` の welcome ページのみ)。

- ベースURL(ローカル): `http://localhost:8000`
- DB: SQLite(`database/database.sqlite`)
- 起動: `php artisan serve`(初回は `php artisan migrate --seed` と `php artisan storage:link`)

## ディレクトリ構成(主要ファイル)

```
api/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── WorkController.php          … 公開: 作品一覧
│   │   │   ├── ContactController.php       … 公開: お問い合わせ送信
│   │   │   ├── AuthController.php          … 公開: 管理者ログイン
│   │   │   └── Admin/AdminWorkController.php … 管理: 作品CRUD・並び替え
│   │   └── Middleware/AdminToken.php       … Bearer トークン認証
│   ├── Mail/ContactMail.php                … お問い合わせメール(Mailable)
│   └── Models/Work.php                     … 作品モデル
├── bootstrap/app.php                       … ミドルウェア alias 'admin.token' 登録
├── config/
│   ├── site.php                            … 管理パスワード・トークン・メール宛先
│   └── cors.php                            … CORS(FRONTEND_URL のみ許可)
├── database/
│   ├── migrations/2026_07_03_000001_create_works_table.php
│   └── seeders/WorkSeeder.php              … ダミー作品6件
├── public/images/                          … シード用サンプル画像(SVG)
├── resources/views/emails/contact.blade.php … メール本文テンプレート
└── routes/api.php                          … ルート定義(prefix: /api)
```

## エンドポイント一覧

### 公開 API(認証不要)

#### GET /api/works — 作品一覧

`sort_order` 降順(同値は `id` 降順)で全件返す。

```json
{
  "works": [
    { "id": 6, "title": "...", "category": "Webデザイン", "description": "...", "image": "http://localhost:8000/images/work1.svg" }
  ],
  "categories": ["Webデザイン", "コーディング", "バナー", "LP"]
}
```

- `image` はモデルのアクセサで絶対URLに変換される(下記「画像の扱い」参照)
- `categories` は登録済み作品のカテゴリを重複排除した配列(表示順は作品順)

#### POST /api/contact — お問い合わせ送信

| パラメータ | 型 | バリデーション |
|---|---|---|
| name | string | required, max:100 |
| email | string | required, email, max:255 |
| kind | string | required, max:100(お問い合わせ種別) |
| message | string | required, max:5000 |

- `config('site.mail_to')`(env: `MAIL_TO_ADDRESS`)宛に `ContactMail` を送信
- 件名: `kind` に「依頼」を含む場合「【制作のご依頼】ポートフォリオサイトより」、それ以外は「【お問い合わせ】ポートフォリオサイトより」
- Reply-To に送信者の name/email を設定
- 成功時: `{ "ok": true }`

#### POST /api/admin/login — 管理者ログイン

| パラメータ | 型 | バリデーション |
|---|---|---|
| password | string | required |

- `config('site.admin_password')`(env: `ADMIN_PASSWORD`)と `hash_equals` で比較
- 成功時: `{ "token": "<ADMIN_API_TOKEN の値>" }`
- 失敗時: 401 `{ "message": "パスワードが違います" }`

### 管理 API(要 Bearer トークン)

`Authorization: Bearer <ADMIN_API_TOKEN>` 必須。`AdminToken` ミドルウェア(alias: `admin.token`)が検証し、不一致・未設定なら 401 `{ "message": "認証が必要です" }`。

#### POST /api/admin/works — 作品登録

multipart/form-data。

| パラメータ | 型 | バリデーション |
|---|---|---|
| title | string | required, max:200 |
| category | string | required, max:100 |
| description | string | nullable, max:5000 |
| image | file | required, image, max:4096(KB) |

- 画像は `storage/app/public/works/` に保存
- `sort_order` は既存最大値 +1(= 新規は先頭に表示)
- 成功時: 201 で作成された Work を返す

#### POST /api/admin/works/{work} — 作品更新

※ 画像アップロードを伴うため PUT ではなく POST。パラメータは登録時と同じだが `image` は nullable(省略時は既存画像を維持)。新画像がある場合は旧画像を削除してから保存。成功時は更新後の Work を返す。

#### DELETE /api/admin/works/{work} — 作品削除

画像ファイルも削除(シード画像 `images/` 配下は削除しない)。成功時: `{ "ok": true }`。

#### POST /api/admin/works-reorder — 並び替え

| パラメータ | 型 | バリデーション |
|---|---|---|
| ids | array | required(表示したい順の作品ID配列) |

配列の先頭ほど大きい `sort_order` を振り直す(先頭 = count、末尾 = 1)。成功時: `{ "ok": true }`。

## データベース

### works テーブル

| カラム | 型 | 備考 |
|---|---|---|
| id | bigint | PK |
| title | string | 作品タイトル |
| category | string | カテゴリ名(自由入力。マスタなし) |
| description | text | nullable。説明文 |
| image_path | string | `images/workN.svg`(シード)or `works/xxx.jpg`(アップロード) |
| sort_order | integer | default 0。大きいほど先頭に表示 |
| created_at / updated_at | timestamp | |

その他 Laravel 標準の users / cache / jobs テーブルあり(現状 users は未使用)。

### シードデータ

`WorkSeeder` がダミー作品6件(Webデザイン2・コーディング1・バナー2・LP1)を `public/images/work1〜6.svg` 付きで投入。

## 画像の扱い

`Work` モデルの `image` アクセサ(`$appends`)が `image_path` を絶対URLへ変換する:

- `images/` で始まる → シード画像。`url(image_path)` = `public/images/` 配下を直接配信
- それ以外 → アップロード画像。`url('storage/' . image_path)` = `storage:link` 経由で配信

削除・差し替え時はシード画像を消さないよう `images/` プレフィックスで判定している。

## 認証方式

1. フロントの `/admin` でパスワードを `POST /api/admin/login` に送る
2. 一致すれば固定トークン(`ADMIN_API_TOKEN`)が返る
3. 以降の管理 API はこのトークンを Bearer ヘッダで送る

トークンはユーザー毎ではなく環境変数の固定値。比較はタイミング攻撃対策で `hash_equals` を使用。

## CORS

`config/cors.php` で `api/*` と `storage/*` に対し、`FRONTEND_URL`(default: `http://localhost:3000`)のオリジンのみ許可。

## 環境変数(.env)

| 変数 | 用途 |
|---|---|
| APP_URL | API の URL(画像URL生成に使用)。ローカル: `http://localhost:8000` |
| DB_CONNECTION | `sqlite` |
| ADMIN_PASSWORD | 管理画面ログインパスワード |
| ADMIN_API_TOKEN | 管理 API の固定トークン(`openssl rand -hex 32` で生成) |
| MAIL_TO_ADDRESS | お問い合わせメールの届け先 |
| MAIL_MAILER | ローカル: `log`(laravel.log に出力)/ 本番: `smtp` |
| FRONTEND_URL | CORS 許可オリジン。ローカル: `http://localhost:3000` |
