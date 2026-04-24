<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ItemsTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function title(): string
    {
        return 'Template Sparepart';
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Nama Sparepart',
            'Kategori',
            'Kode Satuan',
            'Tipe Pergerakan',
            'ERP Item Code',
            'Min Stok',
            'Maks Stok',
            'Reorder Point',
            'Berat KG',
            'Volume M3',
            'Barcode',
            'Deadstock Threshold Hari',
            'Deskripsi',
        ];
    }

    public function array(): array
    {
        return [
            // Contoh baris 1
            [
                'SP-0001',
                'Baut Hexagonal M10x30',
                'Baut & Mur',
                'PCS',
                'fast_moving',
                'ERP-0001',
                10,
                500,
                50,
                0.05,
                '',
                '',
                90,
                'Baut hexagonal baja galvanis',
            ],
            // Contoh baris 2
            [
                'SP-0002',
                'Filter Oli Mesin',
                'Filter',
                'PCS',
                'slow_moving',
                '',
                5,
                100,
                20,
                0.3,
                '',
                '',
                90,
                '',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Header row styling
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 11,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F618D'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
        ]);

        // Example rows styling
        $sheet->getStyle('A2:N3')->applyFromArray([
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'EBF5FB'],
            ],
        ]);

        // Add note row at bottom
        $sheet->setCellValue('A5', '* Kolom wajib: SKU, Nama Sparepart, Kategori, Kode Satuan');
        $sheet->setCellValue('A6', '* Tipe Pergerakan: fast_moving | slow_moving | dead');
        $sheet->setCellValue('A7', '* Kategori dan Kode Satuan harus sesuai dengan data di sistem');
        $sheet->setCellValue('A8', '* Baris contoh di atas (baris 2-3) dapat dihapus sebelum upload');

        $sheet->getStyle('A5:A8')->applyFromArray([
            'font'      => ['italic' => true, 'color' => ['rgb' => '7F8C8D'], 'size' => 9],
        ]);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 30,
            'C' => 20,
            'D' => 14,
            'E' => 16,
            'F' => 16,
            'G' => 10,
            'H' => 11,
            'I' => 15,
            'J' => 10,
            'K' => 12,
            'L' => 14,
            'M' => 24,
            'N' => 30,
        ];
    }
}
