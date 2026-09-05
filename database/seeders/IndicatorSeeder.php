<?php

namespace Database\Seeders;

use App\Models\Indicator;
use App\Models\IndicatorGroup;
use Illuminate\Database\Seeder;

class IndicatorSeeder extends Seeder
{
    /**
     * 24 indikator sesuai §12 spesifikasi induk.
     *
     * Format: [group_code, name, target, unit]
     */
    private const INDICATORS = [
        // IKU — 4
        ['IKU', 'Indeks Daftar Merah Nasional Status Keterancaman Spesies', 0.75, 'Poin'],
        ['IKU', 'Persentase pertumbuhan Produk Domestik Bruto sub sektor kehutanan', 1.80, 'Persen'],
        ['IKU', 'Nilai ekspor produk kehutanan', 15.82, 'Miliar USD'],
        ['IKU', 'Nilai PNBP Fungsional Kehutanan', 8.29, 'Triliun Rupiah'],

        // IKP — 4
        ['IKP', 'Jumlah Spesies yang di Assessment secara Nasional dalam Mendukung IUCN RedList', 5, 'Spesies'],
        ['IKP', 'Penurunan Status Keterancaman Spesies pada IUCN RedList', 5, 'Spesies'],
        ['IKP', 'Nilai Ekspor TSL dan Bioprospecting', 7.80, 'Triliun Rupiah'],
        ['IKP', 'Nilai PNBP dari Pemanfaatan Jasa Lingkungan dan TSL Berkelanjutan', 241.50, 'Miliar Rupiah'],

        // IKK — 4
        ['IKK', 'Jumlah produk yang dikembangkan melalui bioprospeksi', 1, 'Produk'],
        ['IKK', 'Jumlah spesies yang terdata sebaran dan/atau populasinya', 148, 'Spesies'],
        ['IKK', 'Nilai PNBP dari hasil pemanfaatan tumbuhan dan satwa liar berkelanjutan', 26.50, 'Miliar Rupiah'],
        ['IKK', 'Peningkatan Jumlah Individu TSL hasil penangkaran yang dimanfaatkan', 200, 'Individu'],

        // RO — 12
        ['RO', 'Penguatan Data dan Informasi Keanekaragaman Hayati', 74, 'Data'],
        ['RO', 'Layanan Penguatan Pemanfaatan TSL yang Berkelanjutan', 26, 'Layanan'],
        ['RO', 'Optimalisasi Pengelolaan Lembaga Konservasi', 8, 'Lembaga'],
        ['RO', 'Penyelamatan Jenis Tumbuhan dan Satwa Liar', 8, 'Lokasi'],
        ['RO', 'Informasi Pengelolaan Keamanan Hayati', 1, 'Dokumen'],
        ['RO', 'Pengembangan Bioprospeksi Sumber Daya Genetik Spesies Liar', 1, 'Spesies'],
        ['RO', 'Rekomendasi Kebijakan pengembangan bioprospeksi', 1, 'Kebijakan'],
        ['RO', 'Rekomendasi Kebijakan Pengawetan Spesies dan Genetik', 3, 'Kebijakan'],
        ['RO', 'Rekomendasi Kebijakan Pemanfaatan Spesies dan Genetik', 3, 'Kebijakan'],
        ['RO', 'Layanan Pengelolaan PNBP Pemanfaatan TSL', 1, 'Laporan'],
        ['RO', 'Informasi Keterancaman Spesies Indonesia', 1, 'Dokumen'],
        ['RO', 'Pengembangbiakan Spesies Prioritas', 20, 'Spesies'],
    ];

    public function run(): void
    {
        foreach (self::INDICATORS as $i => [$groupCode, $name, $target, $unit]) {
            $group = IndicatorGroup::where('code', $groupCode)->first();

            Indicator::firstOrCreate(
                ['name' => $name],
                [
                    'indicator_group_id' => $group->id,
                    'name' => $name,
                    'target' => $target,
                    'unit' => $unit,
                    'sort_order' => $i + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
