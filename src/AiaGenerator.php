<?php
namespace App;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Exception;
use Monolog\Logger;

class AiaGenerator
{
    private Logger $logger;
    private const CURRENCY_FORMAT = '"$"#,##0.00;[Red]\-"$"#,##0.00';
    private const PERCENTAGE_FORMAT = '0.00%';

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function generateAiaExcel(array $budgetData, array $projectInfo): string
    {
        try {
            $this->logger->debug('Starting AIA G702/G703 Excel generation.');
            $spreadsheet = new Spreadsheet();

            // Remove default sheet
            $spreadsheet->removeSheetByIndex(0);

            // --- Create G703 Continuation Sheet ---
            $g703Sheet = new Worksheet($spreadsheet, 'G703 - Continuation Sheet');
            $spreadsheet->addSheet($g703Sheet, 0);
            $this->generateG703($g703Sheet, $budgetData, $projectInfo);

            // --- Create G702 Application Sheet ---
            $g702Sheet = new Worksheet($spreadsheet, 'G702 - Application');
            $spreadsheet->addSheet($g702Sheet, 1);
            $this->generateG702($g702Sheet, $projectInfo, $g703Sheet); // Pass G703 sheet for totals

            // Set active sheet to G702
            $spreadsheet->setActiveSheetIndexByName('G702 - Application');


            // --- Save the spreadsheet ---
            $writer = new Xlsx($spreadsheet);
            $tempDir = sys_get_temp_dir();
            if (!is_writable($tempDir)) {
                 $this->logger->error('Temporary directory is not writable.', ['directory' => $tempDir]);
                 throw new Exception("Temporary directory is not writable: " . $tempDir);
            }
            $tempFile = tempnam($tempDir, 'AIA_');
            if ($tempFile === false) {
                 $this->logger->error('Failed to create temporary file.', ['directory' => $tempDir]);
                 throw new Exception("Failed to create temporary file in " . $tempDir);
            }
            $writer->save($tempFile);

            $this->logger->info('AIA G702/G703 Excel file generated successfully.', ['temp_file' => $tempFile]);
            return $tempFile;

        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            $this->logger->error("PhpSpreadsheet error during Excel generation.", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw new Exception('Error generating Excel file: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->logger->error("AiaGenerator error during Excel generation.", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    private function generateG703(Worksheet $sheet, array $budgetData, array $projectInfo): void
    {
        $sheet->setTitle('G703 - Continuation Sheet');

        // --- Basic Headers ---
        $sheet->setCellValue('A1', 'APPLICATION AND CERTIFICATE FOR PAYMENT')->mergeCells('A1:I1');
        $sheet->setCellValue('A2', 'Continuation Sheet AIA Document G703')->mergeCells('A2:I2');
        // Add more standard headers like Application Number, Date, Period To, Project Name etc. from $projectInfo
        $sheet->setCellValue('A4', 'Application Number:');
        $sheet->setCellValue('B4', $projectInfo['application_number']);
        $sheet->setCellValue('D4', 'Period To:');
        $sheet->setCellValue('E4', $projectInfo['period_to']);
        // ... add others as needed

        // --- Column Headers ---
        $headerRow = 6;
        $sheet->setCellValue('A'.$headerRow, 'Item No.'); // Often omitted or corresponds to line item ID
        $sheet->setCellValue('B'.$headerRow, 'Description of Work');
        $sheet->setCellValue('C'.$headerRow, 'Scheduled Value');
        $sheet->setCellValue('D'.$headerRow, "Work Completed\nFrom Previous\nApplication (D+E)"); // Often calculated, here we use input
        $sheet->setCellValue('E'.$headerRow, "Work Completed\nThis Period"); // Often calculated, here we use input
        $sheet->setCellValue('F'.$headerRow, 'Materials Presently Stored');
        $sheet->setCellValue('G'.$headerRow, "Total Completed\nand Stored to\nDate (D+E+F)");
        $sheet->setCellValue('H'.$headerRow, '% (G ÷ C)');
        $sheet->setCellValue('I'.$headerRow, "Balance to Finish (C-G)");
        // Optional: Retainage Column
        $sheet->setCellValue('J'.$headerRow, "Retainage");

        // Style Headers
        $headerStyle = [
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9D9D9']]
        ];
        $sheet->getStyle('A'.$headerRow.':J'.$headerRow)->applyFromArray($headerStyle);
        $sheet->getRowDimension($headerRow)->setRowHeight(45);


        // --- Populate Budget Data and Formulas ---
        $dataStartRow = $headerRow + 1;
        $currentRow = $dataStartRow;
        // Get specific retainage rates
        $retainageCompletedPercent = ($projectInfo['retainage_completed_percent'] ?? 10.0) / 100;
        $retainageStoredPercent = ($projectInfo['retainage_stored_percent'] ?? 10.0) / 100;
        // Optional: Get threshold values if implemented
        // $reductionThresholdPercent = ($projectInfo['retainage_reduction_threshold'] ?? null);
        // $reducedRetainagePercent = ($projectInfo['reduced_retainage_percent'] ?? null);

        foreach ($budgetData as $index => $item) {
            $sheet->setCellValue('A' . $currentRow, $index + 1); // Simple item number
            $sheet->setCellValue('B' . $currentRow, $item['description']);
            $sheet->setCellValue('C' . $currentRow, $item['scheduled_value']);
            $sheet->setCellValue('D' . $currentRow, $item['previous_completed']);
            $sheet->setCellValue('E' . $currentRow, $item['current_completed']);
            $sheet->setCellValue('F' . $currentRow, $item['stored_materials']);

            // Formula: Column G = D + E + F
            $sheet->setCellValue('G' . $currentRow, "=SUM(D{$currentRow}:F{$currentRow})");

            // Formula: Column H = G / C (handle division by zero)
            $sheet->setCellValue('H' . $currentRow, "=IF(C{$currentRow}=0,0,G{$currentRow}/C{$currentRow})");

            // Formula: Column I = C - G
            $sheet->setCellValue('I' . $currentRow, "=C{$currentRow}-G{$currentRow}");

            // --- Formula: Column J = Retainage (Variable Rate) ---
            // Calculate retainage on completed work (D+E) and stored materials (F) separately
            $completedWorkCell = "(D{$currentRow}+E{$currentRow})"; // Cells containing completed work value
            $storedMaterialCell = "F{$currentRow}"; // Cell containing stored material value

            // Basic variable rate formula: (Completed Work * Rate1) + (Stored Materials * Rate2)
            $retainageFormula = "=(" . $completedWorkCell . "*" . $retainageCompletedPercent . ")+(" . $storedMaterialCell . "*" . $retainageStoredPercent . ")";

            // --- Optional: Add logic for reduction threshold ---
            // This requires comparing overall project completion % (calculated on G702) to the threshold
            // Since G702 isn't fully calculated yet, this logic is complex to implement purely in G703 formulas.
            // A simpler approach (though less standard) might apply reduction based on individual line item completion (Col H)

            if ($reductionThresholdPercent !== null && $reducedRetainagePercent !== null) {
                $threshold = $reductionThresholdPercent / 100;
                $reducedRate = $reducedRetainagePercent / 100;
                // Example: If line item % complete (H) > threshold, use reduced rate on completed work
                 $retainageFormula = "=IF(H{$currentRow}>{$threshold}," .
                                     "((" . $completedWorkCell . "*" . $reducedRate . ")+(" . $storedMaterialCell . "*" . $retainageStoredPercent . "))," . // Reduced rate case
                                     "((" . $completedWorkCell . "*" . $retainageCompletedPercent . ")+(" . $storedMaterialCell . "*" . $retainageStoredPercent . "))" . // Normal rate case
                                     ")";
            }
 
            // --- End Optional Reduction Logic ---

            $sheet->setCellValue('J' . $currentRow, $retainageFormula);
            // --- End Retainage Formula ---


            $currentRow++;
        }
        $dataEndRow = $currentRow - 1;

        // --- Totals Row ---
        $totalRow = $dataEndRow + 1;
        $sheet->setCellValue('B'.$totalRow, 'TOTALS');
        $sheet->setCellValue('C'.$totalRow, "=SUM(C{$dataStartRow}:C{$dataEndRow})");
        $sheet->setCellValue('D'.$totalRow, "=SUM(D{$dataStartRow}:D{$dataEndRow})");
        $sheet->setCellValue('E'.$totalRow, "=SUM(E{$dataStartRow}:E{$dataEndRow})");
        $sheet->setCellValue('F'.$totalRow, "=SUM(F{$dataStartRow}:F{$dataEndRow})");
        $sheet->setCellValue('G'.$totalRow, "=SUM(G{$dataStartRow}:G{$dataEndRow})");
        // Col H (Percent) Total is not typically summed
        $sheet->setCellValue('I'.$totalRow, "=SUM(I{$dataStartRow}:I{$dataEndRow})");
        $sheet->setCellValue('J'.$totalRow, "=SUM(J{$dataStartRow}:J{$dataEndRow})");

        // Style Totals Row
        $sheet->getStyle('B'.$totalRow.':J'.$totalRow)->getFont()->setBold(true);
        $sheet->getStyle('B'.$totalRow.':J'.$totalRow)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);


        // --- Apply Formatting ---
        // Currency Format for C, D, E, F, G, I, J
        $currencyCols = ['C', 'D', 'E', 'F', 'G', 'I', 'J'];
        foreach ($currencyCols as $col) {
            $sheet->getStyle($col.$dataStartRow.':'.$col.$totalRow)
                  ->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
        }
        // Percentage Format for H
        $sheet->getStyle('H'.$dataStartRow.':H'.$dataEndRow) // Don't format total row for percentage
              ->getNumberFormat()->setFormatCode(self::PERCENTAGE_FORMAT);

        // Borders for data area
        if ($dataStartRow <= $dataEndRow) {
            $sheet->getStyle('A'.$dataStartRow.':J'.$dataEndRow)
                  ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        // --- Column Widths ---
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(40); // Description
        $sheet->getColumnDimension('C')->setWidth(18); // Scheduled Value
        $sheet->getColumnDimension('D')->setWidth(18); // Prev Completed
        $sheet->getColumnDimension('E')->setWidth(18); // This Period
        $sheet->getColumnDimension('F')->setWidth(18); // Stored Materials
        $sheet->getColumnDimension('G')->setWidth(18); // Total Completed
        $sheet->getColumnDimension('H')->setWidth(10); // Percent
        $sheet->getColumnDimension('I')->setWidth(18); // Balance
        $sheet->getColumnDimension('J')->setWidth(18); // Retainage

        // Wrap text for description
        $sheet->getStyle('B'.$dataStartRow.':B'.$dataEndRow)->getAlignment()->setWrapText(true);
    }


    private function generateG702(Worksheet $sheet, array $projectInfo, Worksheet $g703Sheet): void
    {
        $sheet->setTitle('G702 - Application');

        // --- Header Info ---
        // Basic layout, adjust cell positions and add more fields as needed
        $sheet->setCellValue('A1', 'APPLICATION AND CERTIFICATE FOR PAYMENT')->mergeCells('A1:H1')->getStyle('A1')->getFont()->setBold(true);
        $sheet->setCellValue('A3', 'TO OWNER:');
        $sheet->setCellValue('B3', $projectInfo['owner_name']);
        $sheet->setCellValue('A4', 'PROJECT:');
        $sheet->setCellValue('B4', $projectInfo['project_name']);
        $sheet->setCellValue('A5', 'FROM CONTRACTOR:');
        $sheet->setCellValue('B5', $projectInfo['contractor_name']);

        $sheet->setCellValue('F3', 'APPLICATION NO:');
        $sheet->setCellValue('G3', $projectInfo['application_number']);
        $sheet->setCellValue('F4', 'PERIOD TO:');
        $sheet->setCellValue('G4', $projectInfo['period_to']);
        $sheet->setCellValue('F5', 'CONTRACT DATE:');
        $sheet->setCellValue('G5', $projectInfo['contract_date']);

        // --- Application Summary ---
        $summaryStartRow = 8;
        $sheet->setCellValue('A'.$summaryStartRow, 'CONTRACTOR\'S APPLICATION FOR PAYMENT');
        $sheet->getStyle('A'.$summaryStartRow)->getFont()->setBold(true);

        $row = $summaryStartRow + 2;
        $sheet->setCellValue('A'.$row, '1. ORIGINAL CONTRACT SUM');
        $sheet->setCellValue('H'.$row, $projectInfo['original_contract_sum']);
        $line1Cell = 'H'.$row;

        $row++;
        $sheet->setCellValue('A'.$row, '2. Net change by Change Orders');
        $sheet->setCellValue('H'.$row, $projectInfo['change_orders_sum']);
        $line2Cell = 'H'.$row;

        $row++;
        $sheet->setCellValue('A'.$row, '3. CONTRACT SUM TO DATE (Line 1 ± 2)');
        $sheet->setCellValue('H'.$row, "={$line1Cell}+{$line2Cell}"); // Formula
        $line3Cell = 'H'.$row;

        $row++;
        $sheet->setCellValue('A'.$row, '4. TOTAL COMPLETED & STORED TO DATE');
        // Link to G703 Total Column G
        $g703TotalGCell = "'".$g703Sheet->getTitle()."'!G" . ($this->findLastRow($g703Sheet, 'G') ?: 1); // Find last row with data in G703 Col G
        $sheet->setCellValue('H'.$row, "={$g703TotalGCell}"); // Formula
        $line4Cell = 'H'.$row;

        $row++;
        $sheet->setCellValue('A'.$row, '5. RETAINAGE:');
        $line5Cell = 'H'.$row; // Cell for total retainage

        // Link to G703 Total Retainage Column J (This already sums the detailed calculation)
        $g703TotalJCell = "'".$g703Sheet->getTitle()."'!J" . ($this->findLastRow($g703Sheet, 'J') ?: 1);
        $sheet->setCellValue($line5Cell, "={$g703TotalJCell}"); // Link Formula

        // --- Optional: Break down retainage display on G702 ---

        $retainageCompletedPercent = ($projectInfo['retainage_completed_percent'] ?? 10.0) / 100;
        $retainageStoredPercent = ($projectInfo['retainage_stored_percent'] ?? 10.0) / 100;

        // Calculate total completed work (G703 D+E total)
        $g703TotalDECell = "'".$g703Sheet->getTitle()."'!D" . ($this->findLastRow($g703Sheet, 'D') ?: 1) . "+'" . $g703Sheet->getTitle()."'!E" . ($this->findLastRow($g703Sheet, 'E') ?: 1);
        // Calculate total stored materials (G703 F total)
        $g703TotalFCell = "'".$g703Sheet->getTitle()."'!F" . ($this->findLastRow($g703Sheet, 'F') ?: 1);

        $row++; // Move to next row for breakdown
        $sheet->setCellValue('B'.$row, 'a. ' . $projectInfo['retainage_completed_percent'] . '% of Completed Work');
        $sheet->setCellValue('G'.$row, "=(".$g703TotalDECell.")*".$retainageCompletedPercent); // Formula for completed work retainage
        $retainageCompCell = 'G'.$row;

        $row++;
        $sheet->setCellValue('B'.$row, 'b. ' . $projectInfo['retainage_stored_percent'] . '% of Stored Material');
        $sheet->setCellValue('G'.$row, "=(".$g703TotalFCell.")*".$retainageStoredPercent); // Formula for stored material retainage
        $retainageStoredCell = 'G'.$row;

        // Adjust Line 5 label and formula to sum the breakdown
        $sheet->setCellValue('A'.$row, 'Total Retainage (Lines 5a + 5b)');
        $sheet->setCellValue($line5Cell, "={$retainageCompCell}+{$retainageStoredCell}"); // Sum formula

        // --- End Optional Breakdown ---


        $row++;
        $sheet->setCellValue('A'.$row, '6. TOTAL EARNED LESS RETAINAGE');
        $sheet->setCellValue('H'.$row, "={$line4Cell}-{$line5Cell}"); // Formula
        $line6Cell = 'H'.$row;

        $row++;
        $sheet->setCellValue('A'.$row, '7. LESS PREVIOUS CERTIFICATES FOR PAYMENT');
        $sheet->setCellValue('H'.$row, $projectInfo['previous_payments']);
        $line7Cell = 'H'.$row;

        $row++;
        $sheet->setCellValue('A'.$row, '8. CURRENT PAYMENT DUE');
        $sheet->setCellValue('H'.$row, "={$line6Cell}-{$line7Cell}"); // Formula
        $line8Cell = 'H'.$row;

        $row++;
        $sheet->setCellValue('A'.$row, '9. BALANCE TO FINISH, INCLUDING RETAINAGE');
        $sheet->setCellValue('H'.$row, "={$line3Cell}-{$line6Cell}"); // Formula (Contract Sum - Earned Less Retainage)
        $line9Cell = 'H'.$row;

        // --- Formatting ---
        $summaryEndRow = $row;
        // Currency format for summary values
        $sheet->getStyle('H'.($summaryStartRow+2).':H'.$summaryEndRow)
              ->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
        // Borders
        $sheet->getStyle('A'.($summaryStartRow+2).':H'.$summaryEndRow)
              ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A'.($summaryStartRow+2).':A'.$summaryEndRow)
              ->getAlignment()->setWrapText(true);

        // --- Column Widths ---
        $sheet->getColumnDimension('A')->setWidth(50); // Labels
        foreach (range('B', 'G') as $col) {
            $sheet->getColumnDimension($col)->setWidth(2); // Narrow spacer columns
        }
        $sheet->getColumnDimension('H')->setWidth(20); // Values

        // Add Architect Certification, Notary sections etc. as needed (text blocks)
        $row += 3;
        $sheet->setCellValue('A'.$row, 'ARCHITECT\'S CERTIFICATE FOR PAYMENT');
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        // ... add text and signature lines ...

    }

    // Helper to find the last row with data in a specific column of a sheet
    private function findLastRow(Worksheet $sheet, string $column): ?int
    {
        $maxRow = $sheet->getHighestRow($column);
        while ($maxRow > 0) {
            if ($sheet->getCell($column . $maxRow)->getValue() !== null && $sheet->getCell($column . $maxRow)->getValue() !== '') {
                return $maxRow;
            }
            $maxRow--;
        }
        return null; // Or return a default like 1 if needed
    }
}