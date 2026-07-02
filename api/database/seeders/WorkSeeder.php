<?php

namespace Database\Seeders;

use App\Models\Work;
use Illuminate\Database\Seeder;

class WorkSeeder extends Seeder
{
    public function run(): void
    {
        $works = [
            ['カフェ「はなみずき」様 Webサイト', 'Webデザイン', 'images/work1.svg', "自然食カフェのブランドサイトをデザイン。\n担当: デザイン・コーディング / 制作期間: 3週間"],
            ['美容室サイト コーディング', 'コーディング', 'images/work2.svg', "デザインカンプからのコーディング案件。レスポンシブ対応・アニメーション実装を担当。\n担当: コーディング / 制作期間: 2週間"],
            ['春の新生活キャンペーンバナー', 'バナー', 'images/work3.svg', "ECサイトの季節キャンペーン用バナーを複数サイズで制作。\n担当: デザイン / 制作期間: 3日"],
            ['オンラインヨガ教室 LP', 'LP', 'images/work4.svg', "女性向けオンラインヨガ教室のランディングページ。\n担当: デザイン・コーディング / 制作期間: 2週間"],
            ['フラワーショップ「ことは」様 Webサイト', 'Webデザイン', 'images/work5.svg', "お花屋さんのオンラインショップ兼ブランドサイト。\n担当: デザイン・コーディング / 制作期間: 1ヶ月"],
            ['秋のコスメフェア バナーセット', 'バナー', 'images/work6.svg', "コスメブランドの秋フェア用バナー一式。\n担当: デザイン / 制作期間: 5日"],
        ];

        foreach (array_reverse($works) as $i => [$title, $category, $image, $desc]) {
            Work::firstOrCreate(['title' => $title], [
                'category' => $category,
                'image_path' => $image,
                'description' => $desc,
                'sort_order' => $i + 1,
            ]);
        }
    }
}
