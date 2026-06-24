<?php

namespace ApiPerformanceAnalyzer\Console;

use ApiPerformanceAnalyzer\Reporting\ReportBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class ReportCommand extends Command
{
    protected $signature = 'apa:report
                            {--from= : Window start (Y-m-d, default 7 days ago)}
                            {--to= : Window end (Y-m-d, default now)}
                            {--email= : Email the PDF to this address}
                            {--path= : Write the PDF to this filesystem path}';

    protected $description = 'Generate a performance digest (problems + recommendations) as a PDF.';

    public function handle(ReportBuilder $builder): int
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from')) : now()->subDays(7);
        $to = $this->option('to') ? Carbon::parse($this->option('to')) : now();

        $data = $builder->build($from, $to);
        $html = View::make('apa::report.digest', $data)->render();

        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $this->error('barryvdh/laravel-dompdf is not installed. Run: composer require barryvdh/laravel-dompdf');

            return self::FAILURE;
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $binary = $pdf->output();
        $filename = 'apa-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf';

        if ($path = $this->option('path')) {
            file_put_contents($path, $binary);
            $this->info("Wrote {$path}.");
        }

        if ($email = $this->option('email')) {
            Mail::raw('Your API performance digest is attached.', function ($mail) use ($email, $binary, $filename) {
                $mail->to($email)
                    ->subject('API Performance Digest')
                    ->attachData($binary, $filename, ['mime' => 'application/pdf']);
            });
            $this->info("Emailed report to {$email}.");
        }

        if (! $this->option('path') && ! $this->option('email')) {
            $default = storage_path($filename);
            file_put_contents($default, $binary);
            $this->info("Wrote {$default}.");
        }

        return self::SUCCESS;
    }
}
