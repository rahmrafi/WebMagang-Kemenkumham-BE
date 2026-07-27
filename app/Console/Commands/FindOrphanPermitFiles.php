<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\Submission;

class FindOrphanPermitFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permits:find-orphans';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find orphan permit files in the storage that are not referenced in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $disk = Storage::disk('permits');
        
        // 1. List semua file di Storage::disk('permits')
        $allFiles = $disk->allFiles();
        
        // 2. Query semua permit_file_path dari tabel submissions yang tidak null
        $referencedFiles = DB::table('submissions')
            ->whereNotNull('permit_file_path')
            ->pluck('permit_file_path')
            ->toArray();
            
        // 3. Tampilkan file di storage yang TIDAK ada di DB (kandidat orphan)
        $orphanFiles = array_diff($allFiles, $referencedFiles);
        
        $this->info("Orphan Permit Files Check");
        $this->info("-------------------------");
        
        if (count($orphanFiles) > 0) {
            $this->warn("Found " . count($orphanFiles) . " orphan file(s):");
            foreach ($orphanFiles as $file) {
                $this->line("- " . $file);
            }
        } else {
            $this->info("No orphan files found.");
        }
        
        // 4. Tampilkan juga summary
        $this->info("-------------------------");
        $this->info("Summary:");
        $this->info("Total files in storage: " . count($allFiles));
        $this->info("Total referenced in DB: " . count($referencedFiles));
        $this->info("Total orphan files: " . count($orphanFiles));
        
        return 0;
    }
}
