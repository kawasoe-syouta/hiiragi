# フロントエンド仕様書(Next.js)

公開サイトと管理画面を兼ねる Next.js アプリ。実体は現状 `hiiragi-portfolio-site/frontend/` にあり、モノレポ側の `app/frontend/` は placeholder(今後の移設先)。

- URL(ローカル): `http://localhost:3000`
- 起動: `cp .env.local.example .env.local && npm install && npm run dev`
- スタック: Next.js 16(App Router)/ React 19 / TypeScript 5。UI ライブラリ・CSS フレームワークは使わず `app/globals.css` のみ

## ディレクトリ構成

```
frontend/
├── app/
│   ├── layout.tsx        … 共通レイアウト(Header / Footer)
│   ├── globals.css       … 全スタイル
│   ├── page.tsx          … トップページ
│   ├── about/page.tsx    … 自己紹介(経歴・実績・スキル)
│   ├── works/page.tsx    … 作品一覧
│   ├── service/page.tsx  … サービス・料金
│   ├── contact/page.tsx  … お問い合わせフォーム
│   └── admin/page.tsx    … 管理画面(ログイン+作品CRUD)
├── components/
│   ├── Header.tsx / Footer.tsx
│   ├── WorksGrid.tsx     … 作品グリッド(カテゴリ絞り込み・モーダル)
│   ├── CtaBand.tsx       … お問い合わせ導線バンド
│   ├── SnsIcons.tsx      … SNSリンクアイコン
│   └── Flower.tsx / Leaf.tsx … 装飾SVG
├── lib/
│   ├── api.ts            … API クライアント(型定義・fetchWorks)
│   └── site.ts           … サイト情報定数(名前・SNS URL・メール)★要書き換え
├── next.config.mjs
└── .env.local.example    … NEXT_PUBLIC_API_URL=http://localhost:8000
```

## ページ仕様

| パス | 種別 | 内容 |
|---|---|---|
| `/` | Server Component | ヒーロー+作品ピックアップ。`fetchWorks()` で API から取得。**API 停止時も空配列で表示継続**(try/catch) |
| `/about` | 静的 | 経歴・実績・スキル(内容は直接編集して差し替える) |
| `/works` | Server Component | 全作品を `WorksGrid` で表示。カテゴリ絞り込みフィルタ付き |
| `/service` | 静的 | 料金・プラン(内容は直接編集して差し替える) |
| `/contact` | Client Component | 名前・メール・種別・本文のフォーム → `POST /api/contact` |
| `/admin` | Client Component | 管理画面(下記) |

## 管理画面(/admin)の仕様

1ページで「ログイン → 作品管理」を完結させる Client Component。

- **ログイン**: パスワードを `POST /api/admin/login` へ送信 → 返ってきたトークンを `localStorage`(key: `admin_token`)に保存。以降の管理 API 呼び出しに Bearer ヘッダで使用
- **作品登録/編集**: タイトル・カテゴリ・説明のフォーム+画像のドラッグ&ドロップ(プレビュー付き)。multipart で `POST /api/admin/works`(編集は `POST /api/admin/works/{id}`)
- **削除**: `DELETE /api/admin/works/{id}`
- **並び替え**: ID 配列を `POST /api/admin/works-reorder` へ送信
- 保存すると公開側に即反映(公開側は `cache: "no-store"` で毎回取得)

※ `/admin` の URL 自体は公開されるが、パスワード+API トークンで保護。気になる場合は `app/admin` のフォルダ名を変えるだけで URL を変更できる。

## API との連携(lib/api.ts)

```ts
export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

export type Work = {
  id: number;
  title: string;
  category: string;
  description: string;
  image: string;   // 絶対URL(API 側で生成)
};

export type WorksResponse = { works: Work[]; categories: string[] };

fetchWorks(): Promise<WorksResponse>  // GET /api/works, cache: "no-store"
```

- 画像は API が返す絶対 URL をそのまま `<img>` で表示(CORS で `storage/*` も許可済み)
- 環境変数は `NEXT_PUBLIC_API_URL` の1つだけ

## カスタマイズポイント(納品時に差し替える箇所)

| ファイル | 内容 |
|---|---|
| `lib/site.ts` | サイト名・Instagram / X の URL・メールアドレス |
| `app/about/page.tsx` | 経歴・実績・スキル |
| `app/service/page.tsx` | 料金・プラン内容 |
| API 側 `public/images/icon.svg` | アイコン画像(差し替えたらトップ/自己紹介ページの参照も修正) |
