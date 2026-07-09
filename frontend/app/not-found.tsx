import type { Metadata } from "next";
import Link from "next/link";
import Flower from "@/components/Flower";

export const metadata: Metadata = { title: "ページが見つかりません" };

export default function NotFound() {
  return (
    <>
      <div className="page-head">
        <div className="en">404</div>
        <div className="ja">ページが見つかりません</div>
      </div>

      <section className="section">
        <div className="container" style={{ textAlign: "center" }}>
          <div className="section-title">
            <span className="en">Not Found</span>
            <span className="ja">お探しのページが見つかりませんでした</span>
            <Flower />
          </div>
          <p style={{ maxWidth: 560, margin: "0 auto" }}>
            アクセスいただいたページは、移動または削除された可能性があります。<br />
            お手数ですが、トップページからあらためてご覧ください。
          </p>
          <div className="hero-btns" style={{ marginTop: 36 }}>
            <Link href="/" className="btn btn-primary">トップページへ戻る</Link>
            <Link href="/works" className="btn btn-outline">作品を見る</Link>
          </div>
        </div>
      </section>
    </>
  );
}
