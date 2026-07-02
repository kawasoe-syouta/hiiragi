# hiiragi 全体構成仕様書

Webデザイナー/コーダー「ひいらぎ」のポートフォリオサイトを構成するプロジェクト群の全体像。

## リポジトリ構成

ローカルの `hiiragi/` フォルダには **2つの独立した Git リポジトリ** が同居している。

```
hiiragi/                        ← リポジトリ①: hiiragi(モノレポ)
├── README.md
├── api/                        … Laravel 13 API(実際に動く本体)
├── app/
│   └── frontend/               … フロントエンド置き場(現状ほぼ空・開発中)
├── docs/                       … 仕様書(このドキュメント群)
└── hiiragi-portfolio-site/     ← リポジトリ②: hiiragi-portfolio-site(配布用一式)
    ├── README.md
    ├── SETUP.md                … セットアップ手順書
    ├── backend/                … Laravel に上書きコピーするファイル一式(api/ の元ネタ)
    └── frontend/               … Next.js 16 プロジェクト(公開サイト+管理画面)
```

| リポジトリ | GitHub | 役割 |
|---|---|---|
| hiiragi | kawasoe-syouta/hiiragi | 開発用モノレポ。`api/` に Laravel プロジェクト本体を持つ |
| hiiragi-portfolio-site | kawasoe-syouta/hiiragi-portfolio-site | 配布・セットアップ用一式。`backend/` は素の Laravel に上書きする差分ファイル集、`frontend/` は Next.js 本体 |

### 2リポジトリの関係

- `hiiragi/api/` は、素の Laravel 13 プロジェクトに `hiiragi-portfolio-site/backend/` の内容を上書き適用した **動作する状態の API**。
- Next.js フロントエンドの実体は現状 `hiiragi-portfolio-site/frontend/` にのみ存在する。モノレポ側の `app/frontend/` は `.gitkeep` のみの placeholder(今後ここに移す想定)。

## システム構成

```
[ブラウザ]
   │
   ├─ http://localhost:3000  Next.js 16(公開サイト+管理画面 /admin)
   │        │  fetch (CORS: FRONTEND_URL のみ許可)
   │        ▼
   └─ http://localhost:8000  Laravel 13 API(API専用・Blade画面なし)
            │
            ├─ SQLite(api/database/database.sqlite)… 作品データ
            ├─ storage/app/public/works/ … アップロード画像(storage:link で公開)
            ├─ public/images/ … シード用サンプル画像(SVG)
            └─ Mail(お問い合わせ送信。ローカルは MAIL_MAILER=log)
```

## 技術スタック

| レイヤ | 技術 |
|---|---|
| フロントエンド | Next.js 16 / React 19 / TypeScript 5(App Router) |
| バックエンド | Laravel 13(PHP 8.3+)API専用 |
| DB | SQLite |
| 認証 | パスワードログイン → 固定 Bearer トークン(`ADMIN_API_TOKEN`) |
| メール | Laravel Mailable(ローカル: log / 本番: SMTP 例 Gmail) |

## 機能一覧

| 機能 | フロント | API |
|---|---|---|
| 作品一覧表示(カテゴリ絞り込み) | `/works`, トップ | `GET /api/works` |
| お問い合わせフォーム(メール送信) | `/contact` | `POST /api/contact` |
| 管理者ログイン | `/admin` | `POST /api/admin/login` |
| 作品の登録・編集・削除・並び替え(画像アップロード込み) | `/admin` | `POST/DELETE /api/admin/works*` |

## 各仕様書へのリンク

- [api.md](./api.md) … Laravel API 仕様(エンドポイント・DB・認証・メール)
- [frontend.md](./frontend.md) … Next.js フロントエンド仕様(ページ・コンポーネント)
- [portfolio-site.md](./portfolio-site.md) … hiiragi-portfolio-site リポジトリ仕様(配布用一式・backend 上書きファイル・セットアップフロー)
- セットアップ手順 … [hiiragi-portfolio-site/SETUP.md](../hiiragi-portfolio-site/SETUP.md)
