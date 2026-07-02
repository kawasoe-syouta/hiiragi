# hiiragi-portfolio-site 仕様書

Webデザイナー/コーダー「ひいらぎ」のポートフォリオサイト **配布用一式** のリポジトリ(GitHub: kawasoe-syouta/hiiragi-portfolio-site)。

モノレポ(hiiragi)とは別の独立した Git リポジトリで、ローカルでは `hiiragi/hiiragi-portfolio-site/` に同居している。

## リポジトリの役割

「素の Laravel プロジェクトに上書きするバックエンド差分」+「そのまま使える Next.js フロントエンド」+「セットアップ手順書」をまとめた **納品・再現用キット**。

- `backend/` はそれ単体では動かない。`composer create-project laravel/laravel` で作ったプロジェクトに上書きコピーして使う(Laravel 13 標準構成が前提)
- モノレポ側の `hiiragi/api/` は、この上書きを適用済みの「動く状態」の Laravel プロジェクト。**backend/ の全ファイルは api/ 内の同名ファイルと完全一致**(確認済み)
- `frontend/` は Next.js プロジェクトそのもので、`npm install` すればそのまま動く

```
hiiragi-portfolio-site/
├── README.md      … 概要
├── SETUP.md       … セットアップ手順書(下記参照)
├── backend/       … Laravel 上書きファイル一式(22ファイル)
└── frontend/      … Next.js 16 プロジェクト(約1,300行)
```

## backend/ の内容(Laravel 上書きファイル)

| パス | 種別 | 内容 |
|---|---|---|
| `routes/api.php` | 新規 | 公開3+管理4のエンドポイント定義 |
| `app/Http/Controllers/Api/…` | 新規 | Work / Contact / Auth / AdminWork の4コントローラ |
| `app/Http/Middleware/AdminToken.php` | 新規 | Bearer トークン認証 |
| `app/Models/Work.php` | 新規 | 作品モデル(image アクセサ付き) |
| `app/Mail/ContactMail.php` | 新規 | お問い合わせメール(件名は種別で切り替え・Reply-To 設定) |
| `resources/views/emails/contact.blade.php` | 新規 | メール本文(名前・メール・種別・本文を整形) |
| `config/site.php` | 新規 | ADMIN_PASSWORD / ADMIN_API_TOKEN / MAIL_TO_ADDRESS の受け口 |
| `config/cors.php` | 新規 | `api/*` `storage/*` を FRONTEND_URL のみに許可 |
| `database/migrations/…create_works_table.php` | 新規 | works テーブル |
| `database/seeders/WorkSeeder.php` | 新規 | ダミー作品6件(`firstOrCreate` なので再実行しても重複しない) |
| `public/images/*.svg` | 新規 | シード用サンプル画像6点+アイコン |
| `bootstrap/app.php` | **上書き** | ミドルウェア alias `admin.token` を登録 |
| `database/seeders/DatabaseSeeder.php` | **上書き** | WorkSeeder を呼ぶだけに変更 |

既存ファイルを上書きするのは `bootstrap/app.php` と `DatabaseSeeder.php` の2つだけで、残りは新規追加。

API の詳細仕様(エンドポイント・バリデーション・DB定義)は [api.md](./api.md) を参照(内容は同一)。

## frontend/ の内容(Next.js 16)

ページ・コンポーネント・API 連携の詳細は [frontend.md](./frontend.md) を参照。ここではリポジトリ固有の情報のみ:

- 依存は `next` `react` `react-dom` + TypeScript 型定義のみ。UI ライブラリなし(`globals.css` 380行で全スタイル)
- フォント: Zen Maru Gothic(和文)+ Cormorant Garamond(欧文)を `next/font/google` で読み込み
- デザイントーン: 緑×ピンクの手描き風 SVG 装飾(Flower / Leaf コンポーネント)による「やさしい・自然のぬくもり」系
- お問い合わせ種別(KINDS)は5種: Webサイト制作 / LP制作 / バナー制作 / コーディング代行 / その他。`/contact?kind=request` で「制作のご依頼」を初期選択
- 環境変数は `NEXT_PUBLIC_API_URL` のみ(`.env.local.example` をコピーして使う)

## セットアップフロー(SETUP.md の要約)

前提ツール: Laravel Herd(PHP + Composer)と Node.js LTS。

1. **バックエンド**: `composer create-project laravel/laravel hiiragi-api` → `backend/` の中身を上書きコピー → `php artisan storage:link` → `.env` に `ADMIN_PASSWORD` / `ADMIN_API_TOKEN` / `MAIL_TO_ADDRESS` / `FRONTEND_URL` 等を設定 → `php artisan migrate --seed` → `php artisan serve`(http://localhost:8000)
2. **フロントエンド**: `frontend/` で `cp .env.local.example .env.local` → `npm install` → `npm run dev`(http://localhost:3000)
3. **動作確認**: トップで作品表示 / `/admin` にパスワードでログインして作品投稿 / `/contact` から送信(`MAIL_MAILER=log` の間は `storage/logs/laravel.log` に出力)
4. **本番メール**: Gmail のアプリパスワードを発行して SMTP 設定に切り替え(手順は SETUP.md 参照)

## 納品時の差し替えポイント(SETUP.md より)

| 場所 | 内容 |
|---|---|
| `frontend/lib/site.ts` | サイト名・SNS URL・メールアドレス |
| `frontend/app/about/page.tsx` | 経歴・実績・スキル |
| `frontend/app/service/page.tsx` | 料金・プラン |
| `backend/public/images/icon.svg` | アイコン画像 |
| `frontend/app/admin` フォルダ名 | 管理画面の URL を変えたい場合はフォルダ名を変更するだけ |
