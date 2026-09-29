<?php

namespace App\Http\Services;

use App\Models\LeaseContract;
use App\Models\LeaseInstallment;
use App\Models\User;
use App\Support\PermissionAccess;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class LeasePdfService
{
    /**
     * Convert number to Vietnamese words.
     */
    public static function numberToVietnameseWords(float $number): string
    {
        $number = round($number);
        if ($number <= 0) {
            return 'Không đồng';
        }

        $units = ['', ' nghìn', ' triệu', ' tỷ', ' nghìn tỷ', ' triệu tỷ'];
        $digits = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];

        $res = '';
        $unitIdx = 0;

        while ($number > 0) {
            $chunk = $number % 1000;
            if ($chunk > 0) {
                $hundreds = (int) ($chunk / 100);
                $tens = (int) (($chunk % 100) / 10);
                $ones = $chunk % 10;

                $chunkStr = '';
                if ($hundreds > 0 || $number >= 1000) {
                    $chunkStr .= $digits[$hundreds] . ' trăm ';
                }

                if ($tens > 1) {
                    $chunkStr .= $digits[$tens] . ' mươi ';
                    if ($ones === 1) {
                        $chunkStr .= 'mốt';
                    } elseif ($ones === 5) {
                        $chunkStr .= 'lăm';
                    } elseif ($ones > 0) {
                        $chunkStr .= $digits[$ones];
                    }
                } elseif ($tens === 1) {
                    $chunkStr .= 'mười ';
                    if ($ones === 5) {
                        $chunkStr .= 'lăm';
                    } elseif ($ones > 0) {
                        $chunkStr .= $digits[$ones];
                    }
                } else {
                    if ($ones > 0) {
                        if ($hundreds > 0 || $number >= 1000) {
                            $chunkStr .= 'lẻ ';
                        }
                        $chunkStr .= $digits[$ones];
                    }
                }

                $res = trim($chunkStr) . $units[$unitIdx] . ' ' . $res;
            }
            $number = (int) ($number / 1000);
            $unitIdx++;
        }

        $res = trim($res);
        $firstChar = mb_strtoupper(mb_substr($res, 0, 1, 'UTF-8'), 'UTF-8');
        $rest = mb_substr($res, 1, null, 'UTF-8');
        return $firstChar . $rest . ' đồng chẵn';
    }

    /**
     * Generate Lease-to-own Contract PDF binary from stored snapshot.
     */
    public function generateContractPdf(LeaseContract $contract, User $actor): string
    {
        PermissionAccess::can($actor, 'lease.view');
        app(LeaseContractService::class)->authorizeContract($contract, $actor);

        $contract->loadMissing(['customer', 'vehicle', 'store', 'installments', 'allocations']);

        // 1. Ensure snapshot is captured and locked
        if (empty($contract->document_snapshot) || empty($contract->document_snapshot_hash)) {
            $snapshotService = app(LeaseDocumentSnapshotService::class);
            $snapshotService->captureSnapshot($contract, $actor->id);
            $contract->refresh();
        }

        $snap = $contract->document_snapshot;
        $hash = $contract->document_snapshot_hash ?? 'N/A';
        $version = $contract->document_snapshot_version ?? '1.0';

        $computedHash = hash('sha256', json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (!is_string($hash) || !hash_equals($hash, $computedHash)) {
            throw ValidationException::withMessages([
                'document_snapshot' => 'Snapshot hợp đồng không còn khớp checksum; đã chặn xuất PDF để bảo vệ tính toàn vẹn.',
            ]);
        }

        // 2. Read strictly from immutable snapshot payload
        $code = $snap['contract_code'] ?? ($contract->contract_code ?? ('HD-' . str_pad($contract->id, 5, '0', STR_PAD_LEFT)));
        $createdAt = isset($snap['created_at']) ? Carbon::parse($snap['created_at'])->format('d/m/Y H:i') : ($contract->created_at ? $contract->created_at->format('d/m/Y H:i') : Carbon::now()->format('d/m/Y H:i'));
        $storeName = $snap['parties']['lessor']['store_name'] ?? ($contract->store ? ($contract->store->store_name ?? 'HIMOTO') : 'HIMOTO');
        $lessorName = $snap['parties']['lessor']['company_name'] ?? config('contract.company_name');
        $customerName = $snap['parties']['lessee']['name'] ?? 'N/A';
        $phone = $snap['parties']['lessee']['phone'] ?? 'N/A';
        $idCard = $snap['parties']['lessee']['id_card'] ?? 'N/A';

        $plate = $snap['vehicle']['license_plate'] ?? ($contract->vehicle ? ($contract->vehicle->license ?: 'N/A') : 'N/A');
        $model = $snap['vehicle']['name'] ?? ($contract->vehicle ? ($contract->vehicle->name ?: 'Xe may dien HIMOTO') : 'Xe may dien HIMOTO');
        $chassis = $snap['vehicle']['chassis_number'] ?? ($contract->vehicle ? ($contract->vehicle->chassis ?: 'N/A') : 'N/A');
        $engine = $snap['vehicle']['engine_number'] ?? ($contract->vehicle ? ($contract->vehicle->engine ?: 'N/A') : 'N/A');

        $totalAmount = (float) ($snap['financial_terms']['total_amount'] ?? $contract->total_amount);
        $deposit = (float) ($snap['financial_terms']['deposit_amount'] ?? $contract->deposit_amount);
        $monthly = (float) ($snap['financial_terms']['period_amount'] ?? ($contract->period_amount ?? 0));
        $termMonths = (int) ($snap['financial_terms']['installment_count'] ?? ($contract->installment_count ?? ($contract->installments->count() ?: 12)));
        $amountInWords = $snap['financial_terms']['total_amount_in_words'] ?? self::numberToVietnameseWords($totalAmount);

        // Audit view/export
        AuditService::log('lease.contract.pdf_export', $contract, null, [
            'contract_code' => $code,
            'actor_id' => $actor->id,
            'snapshot_hash' => $hash,
            'timestamp' => Carbon::now()->toDateTimeString(),
        ], 'Xuất PDF hợp đồng thuê sở hữu', $contract->store_id, $actor->id);

        return $this->buildPdfDocument([
            'title' => 'HOP DONG THUE SO HUU XE DIEN',
            'doc_code' => $code,
            'subtitle' => 'HIMOTO ELECTRIC VEHICLES - LEASE-TO-OWN CONTRACT',
            'meta' => [
                'Ma hop dong' => $code,
                'Ngay lap' => $createdAt,
                'Co so' => $this->sanitizeAscii($storeName),
                'Phien ban snapshot' => 'v' . $version . '-immutable',
                'Checksum SHA256' => substr($hash, 0, 16) . '...',
            ],
            'sections' => [
                'BEN CHO THUÊ (BEN A)' => [
                    'Don vi' => $this->sanitizeAscii($lessorName),
                    'Dia diem' => $this->sanitizeAscii($storeName),
                    'Dai dien' => 'Giam doc chi nhanh / Nguoi dai dien theo phap luat',
                ],
                'BEN THUE SO HUU (BEN B)' => [
                    'Ho va ten' => $this->sanitizeAscii($customerName),
                    'So dien thoai' => $phone,
                    'So CCCD' => $idCard,
                ],
                'THONG TIN PHUONG TIEN THUE SO HUU' => [
                    'Dong xe' => $this->sanitizeAscii($model),
                    'Bien so' => $plate,
                    'So khung' => $chassis,
                    'So may' => $engine,
                ],
                'GIA TRI VA PHUONG THUC THANH TOAN' => [
                    'Tong gia tri hop dong' => number_format($totalAmount, 0, ',', '.') . ' VND',
                    'Bang chu' => $this->sanitizeAscii($amountInWords),
                    'Tien dat coc ban dau' => number_format($deposit, 0, ',', '.') . ' VND',
                    'Thoi han thue so huu' => $termMonths . ' thang (' . $termMonths . ' ky)',
                    'Tien thanh toan moi ky' => number_format($monthly, 0, ',', '.') . ' VND/ky',
                ],
            ],
            'installments' => collect($snap['installments'] ?? [])->map(function ($inst) {
                $num = $inst['period_number'] ?? null;
                $due = $inst['amount_due'] ?? 0;
                $paid = $inst['amount_paid'] ?? 0;
                $status = $inst['status'] ?? LeaseInstallment::STATUS_UNPAID;
                $st = $status === LeaseInstallment::STATUS_PAID ? 'DA TRA' : (($status === LeaseInstallment::STATUS_PARTIALLY_PAID || $status === 'partial') ? 'TRA 1 PHAN' : 'CHUA TRA');
                return [
                    'ky' => $num,
                    'han' => !empty($inst['due_date']) ? Carbon::parse($inst['due_date'])->format('d/m/Y') : 'N/A',
                    'so_tien' => number_format((float) $due, 0, ',', '.') . ' đ',
                    'da_tra' => number_format((float) $paid, 0, ',', '.') . ' đ',
                    'trang_thai' => $st,
                ];
            })->toArray(),
            'notes' => 'Quyen so huu xe se duoc Ben A chuyen giao cho Ben B sau khi Ben B hoan thanh 100% nghia vu thanh toan theo hop dong nay.',
        ]);
    }

    /**
     * Generate Debt Statement PDF binary.
     */
    public function generateDebtStatementPdf(LeaseContract $contract, User $actor): string
    {
        PermissionAccess::can($actor, 'lease.view');
        app(LeaseContractService::class)->authorizeContract($contract, $actor);

        $contract->loadMissing(['customer', 'vehicle', 'store', 'installments', 'allocations']);

        $code = $contract->contract_code ?? ('HD-' . str_pad($contract->id, 5, '0', STR_PAD_LEFT));
        $now = Carbon::now();

        $activePaid = (float) $contract->allocations()->effectivePayments()->sum('amount');
        $discount = (float) ($contract->discount_amount ?? 0);
        $deposit = (float) $contract->deposit_amount;
        // deposit_amount is the contractual deposit obligation. It is only
        // received when an active payment allocation proves the collection.
        $totalReceived = $activePaid;
        $totalAmount = (float) $contract->total_amount;
        $remainingDebt = max(0, $totalAmount - $totalReceived - $discount);
        $overdueCount = $contract->installments()->where('status', '!=', LeaseInstallment::STATUS_PAID)
            ->where('due_date', '<', $now->toDateString())->count();

        AuditService::log('lease.debt_statement.pdf_export', $contract, null, [
            'contract_code' => $code,
            'actor_id' => $actor->id,
            'timestamp' => $now->toDateTimeString(),
        ], 'Xuất PDF bảng đối soát công nợ thuê sở hữu', $contract->store_id, $actor->id);

        return $this->buildPdfDocument([
            'title' => 'BANG DOI SOAT CONG NO THUE SO HUU',
            'doc_code' => 'STATEMENT-' . $code . '-' . $now->format('Ymd'),
            'subtitle' => 'HIMOTO ELECTRIC VEHICLES - DEBT STATEMENT',
            'meta' => [
                'Ma hop dong' => $code,
                'Khach hang' => $this->sanitizeAscii($contract->customer ? $contract->customer->name : 'N/A'),
                'Thoi diem chot so' => $now->format('d/m/Y H:i:s'),
                    'Co so quan ly' => $this->sanitizeAscii($contract->store ? ($contract->store->store_name ?? $contract->store->name) : 'HIMOTO'),
            ],
            'sections' => [
                'TONG HOP CONG NO' => [
                    'Tong gia tri hop dong' => number_format($totalAmount, 0, ',', '.') . ' VND',
                    'Tien coc theo hop dong' => number_format($deposit, 0, ',', '.') . ' VND',
                    'Tong tien da thu' => number_format($activePaid, 0, ',', '.') . ' VND',
                    'Chiet khau / giam tru' => number_format($discount, 0, ',', '.') . ' VND',
                    'Tong tien da ghi nhan' => number_format($totalReceived + $discount, 0, ',', '.') . ' VND',
                    'DU NO CON LAI' => number_format($remainingDebt, 0, ',', '.') . ' VND',
                    'So ky qua han' => $overdueCount . ' ky',
                    'Trang thai du no' => $remainingDebt <= 0 ? 'DA TAT TOAN HET NO' : 'DANG CON DU NO',
                ],
            ],
            'installments' => $contract->installments->map(function ($inst) {
                $num = $inst->period_number ?? $inst->installment_number;
                $due = $inst->amount_due ?? $inst->amount;
                $paid = $inst->amount_paid ?? $inst->paid_amount;
                $st = $inst->status === LeaseInstallment::STATUS_PAID ? 'DA TRA' : (($inst->status === LeaseInstallment::STATUS_PARTIALLY_PAID || $inst->status === 'partial') ? 'TRA 1 PHAN' : 'CHUA TRA');
                return [
                    'ky' => $num,
                    'han' => $inst->due_date ? Carbon::parse($inst->due_date)->format('d/m/Y') : 'N/A',
                    'so_tien' => number_format((float) $due, 0, ',', '.') . ' đ',
                    'da_tra' => number_format((float) $paid, 0, ',', '.') . ' đ',
                    'trang_thai' => $st,
                ];
            })->toArray(),
            'notes' => 'So lieu duoc doi soat tu dong tu he thong ke toan - phan bo HIMOTO. Moi thac mac vui long lien he phong Ke toan.',
        ]);
    }

    /**
     * Build clean standard PDF-1.4 binary content with A4 coordinates (595.28 x 841.89 pt).
     */
    protected function buildPdfDocument(array $data): string
    {
        $objects = [];
        
        // 1. Catalog
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        
        // 2. Pages
        $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        
        // 4. Font - Helvetica
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        // 3. Page (A4 portrait: 595.28 x 841.89)
        $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>";

        // Stream Content (Object 6)
        $stream = $this->buildPdfStream($data);
        $streamLen = strlen($stream);
        $objects[6] = "<< /Length {$streamLen} >>\nstream\n{$stream}\nendstream";

        // Construct PDF file
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($out);
            $out .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($out);
        $out .= "xref\n0 " . (count($objects) + 1) . "\n";
        $out .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $out .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $out .= "startxref\n{$xrefOffset}\n%%EOF";

        return $out;
    }

    /**
     * Construct PDF stream commands for layout.
     */
    protected function buildPdfStream(array $data): string
    {
        $lines = [];
        
        // Top header band (Dark Slate Blue)
        $lines[] = "0.10 0.16 0.28 rg"; // Fill color #1a2942
        $lines[] = "30 780 535 45 re f"; // Rectangle

        // Header Text
        $lines[] = "1.0 1.0 1.0 rg"; // White text
        $lines[] = "BT /F2 16 Tf 45 798 Td (HIMOTO ELECTRIC VEHICLES) Tj ET";
        $lines[] = "BT /F1 9 Tf 45 786 Td (" . $this->escapePdf($data['subtitle'] ?? '') . ") Tj ET";

        // Document Title & Code
        $lines[] = "0.12 0.18 0.24 rg";
        $lines[] = "BT /F2 14 Tf 45 745 Td (" . $this->escapePdf($data['title'] ?? '') . ") Tj ET";
        $lines[] = "0.4 0.4 0.4 rg";
        $lines[] = "BT /F1 9 Tf 45 732 Td (Ma van ban: " . $this->escapePdf($data['doc_code'] ?? '') . ") Tj ET";

        // Separator line
        $lines[] = "0.8 0.82 0.85 RG 1 w";
        $lines[] = "45 722 m 550 722 l S";

        $currentY = 705;

        // Meta Box
        if (!empty($data['meta'])) {
            $lines[] = "0.96 0.97 0.98 rg 45 " . ($currentY - 40) . " 505 45 re f";
            $lines[] = "0.85 0.87 0.90 RG 0.5 w 45 " . ($currentY - 40) . " 505 45 re S";
            
            $metaY = $currentY - 15;
            foreach ($data['meta'] as $k => $v) {
                $lines[] = "0.2 0.2 0.2 rg";
                $lines[] = "BT /F2 8 Tf 55 {$metaY} Td (" . $this->escapePdf($k) . ":) Tj ET";
                $lines[] = "BT /F1 8 Tf 150 {$metaY} Td (" . $this->escapePdf((string)$v) . ") Tj ET";
                $metaY -= 12;
            }
            $currentY -= 55;
        }

        // Sections
        if (!empty($data['sections'])) {
            foreach ($data['sections'] as $secTitle => $fields) {
                $lines[] = "0.15 0.25 0.45 rg";
                $lines[] = "BT /F2 9.5 Tf 45 {$currentY} Td (" . $this->escapePdf($secTitle) . ") Tj ET";
                $currentY -= 14;

                foreach ($fields as $fk => $fv) {
                    $lines[] = "0.3 0.3 0.3 rg";
                    $lines[] = "BT /F2 8 Tf 55 {$currentY} Td (" . $this->escapePdf($fk) . ":) Tj ET";
                    $lines[] = "0.1 0.1 0.1 rg";
                    $lines[] = "BT /F1 8 Tf 180 {$currentY} Td (" . $this->escapePdf((string)$fv) . ") Tj ET";
                    $currentY -= 12;
                }
                $currentY -= 6;
            }
        }

        // Installments schedule table (first 8 rows to guarantee single page fit)
        if (!empty($data['installments'])) {
            $lines[] = "0.15 0.25 0.45 rg";
            $lines[] = "BT /F2 9.5 Tf 45 {$currentY} Td (LICH THANH TOAN KY (SNAPSHOT)) Tj ET";
            $currentY -= 14;

            // Table header
            $lines[] = "0.92 0.94 0.96 rg 45 " . ($currentY - 4) . " 505 14 re f";
            $lines[] = "0.1 0.1 0.1 rg";
            $lines[] = "BT /F2 7.5 Tf 55 {$currentY} Td (Ky) Tj 100 {$currentY} Td (Han thanh toan) Tj 220 {$currentY} Td (So tien ky) Tj 340 {$currentY} Td (Da tra) Tj 450 {$currentY} Td (Trang thai) Tj ET";
            $currentY -= 14;

            $items = array_slice($data['installments'], 0, 12);
            foreach ($items as $inst) {
                $lines[] = "0.2 0.2 0.2 rg";
                $lines[] = "BT /F1 7 Tf 55 {$currentY} Td (" . $this->escapePdf((string)$inst['ky']) . ") Tj " .
                    "100 {$currentY} Td (" . $this->escapePdf($inst['han']) . ") Tj " .
                    "220 {$currentY} Td (" . $this->escapePdf($inst['so_tien']) . ") Tj " .
                    "340 {$currentY} Td (" . $this->escapePdf($inst['da_tra']) . ") Tj " .
                    "450 {$currentY} Td (" . $this->escapePdf($inst['trang_thai']) . ") Tj ET";
                $currentY -= 11;
            }
            if (count($data['installments']) > 12) {
                $lines[] = "0.5 0.5 0.5 rg BT /F1 7 Tf 55 {$currentY} Td (... Con tiep tuc theo danh sach ky hop dong ...) Tj ET";
                $currentY -= 12;
            }
        }

        // Notes and Signatures
        $currentY -= 10;
        if (!empty($data['notes'])) {
            $lines[] = "0.4 0.4 0.4 rg";
            $lines[] = "BT /F1 7.5 Tf 45 {$currentY} Td (" . $this->escapePdf($data['notes']) . ") Tj ET";
            $currentY -= 25;
        }

        // Signatures area at bottom
        $lines[] = "0.2 0.2 0.2 rg";
        $lines[] = "BT /F2 8.5 Tf 90 {$currentY} Td (DAI DIEN BEN A) Tj 380 {$currentY} Td (DAI DIEN BEN B) Tj ET";
        $lines[] = "0.4 0.4 0.4 rg";
        $lines[] = "BT /F1 7.5 Tf 80 " . ($currentY - 12) . " Td ((Ky va ghi ro ho ten)) Tj 370 " . ($currentY - 12) . " Td ((Ky va ghi ro ho ten)) Tj ET";

        // Footer
        $lines[] = "0.55 0.55 0.55 rg";
        $lines[] = "BT /F1 7 Tf 45 25 Td (HIMOTO EV Platform - Van ban tao tu dong tu snapshot he thong - Trang 1/1) Tj ET";

        return implode("\n", $lines);
    }

    /**
     * Escape special PDF string characters.
     */
    protected function escapePdf(?string $str): string
    {
        if ($str === null || $str === '') {
            return '';
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $str);
    }

    /**
     * Transliterate Vietnamese Unicode to clean standard ASCII for PDF WinAnsi compliance.
     */
    protected function sanitizeAscii(?string $str): string
    {
        if ($str === null || $str === '') {
            return '';
        }
        $unicode = [
            'a' => 'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
            'd' => 'đ',
            'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
            'i' => 'í|ì|ỉ|ĩ|ị',
            'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
            'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
            'y' => 'ý|ỳ|ỷ|ỹ|ỵ',
            'A' => 'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ặ|Ằ|Ẳ|Ẵ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
            'D' => 'Đ',
            'E' => 'É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
            'I' => 'Í|Ì|Ỉ|Ĩ|Ị',
            'O' => 'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
            'U' => 'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
            'Y' => 'Ý|Ỳ|Ỷ|Ỹ|Ỵ',
        ];
        foreach ($unicode as $nonAccent => $accent) {
            $str = preg_replace("/($accent)/i", $nonAccent, $str);
        }
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str) ?: $str;
    }
}
