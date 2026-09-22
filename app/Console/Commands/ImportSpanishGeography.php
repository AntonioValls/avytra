<?php

namespace App\Console\Commands;

use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Loads (or refreshes) the Spanish geographic catalogue from database/data/spain/*.csv.
 * Idempotent: rows are upserted by INE code, so it can run on every deploy.
 * Sources and processing are documented in database/data/spain/README.md.
 */
#[Signature('avytra:import-geography {--path= : Directory containing regions.csv, provinces.csv and municipalities.csv}')]
#[Description('Import the Spanish regions, provinces and municipalities catalogue from CSV files.')]
class ImportSpanishGeography extends Command
{
    private const string COUNTRY = 'ES';

    private const int CHUNK = 500;

    public function handle(): int
    {
        $path = rtrim((string) ($this->option('path') ?: database_path('data/spain')), '/\\');

        foreach (['regions', 'provinces', 'municipalities'] as $file) {
            if (! is_file("{$path}/{$file}.csv")) {
                $this->error("Missing {$path}/{$file}.csv");

                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($path): void {
            $this->importRegions("{$path}/regions.csv");
            $this->importProvinces("{$path}/provinces.csv");
            $this->importMunicipalities("{$path}/municipalities.csv");
        });

        $this->info(sprintf(
            'Geography imported: %d regions, %d provinces, %d municipalities.',
            Region::query()->where('country_code', self::COUNTRY)->count(),
            Province::query()->where('country_code', self::COUNTRY)->count(),
            Municipality::query()->where('country_code', self::COUNTRY)->count(),
        ));

        return self::SUCCESS;
    }

    private function importRegions(string $file): void
    {
        $rows = [];

        foreach ($this->rows($file) as $row) {
            $rows[] = [
                'country_code' => self::COUNTRY,
                'code' => $row['code'],
                'name' => $row['name'],
                'slug' => Str::slug($row['name']),
            ];
        }

        Region::query()->upsert($rows, ['country_code', 'code'], ['name', 'slug']);
    }

    private function importProvinces(string $file): void
    {
        $regionIds = Region::query()->where('country_code', self::COUNTRY)->pluck('id', 'code');
        $rows = [];

        foreach ($this->rows($file) as $row) {
            $rows[] = [
                'region_id' => $regionIds[$row['region_code']]
                    ?? throw new RuntimeException("Unknown region code [{$row['region_code']}] for province {$row['code']}."),
                'country_code' => self::COUNTRY,
                'code' => $row['code'],
                'name' => $row['name'],
                'slug' => Str::slug($row['name']),
                'latitude' => $row['latitude'] !== '' ? (float) $row['latitude'] : null,
                'longitude' => $row['longitude'] !== '' ? (float) $row['longitude'] : null,
            ];
        }

        Province::query()->upsert($rows, ['country_code', 'code'], ['region_id', 'name', 'slug', 'latitude', 'longitude']);
    }

    private function importMunicipalities(string $file): void
    {
        $provinceIds = Province::query()->where('country_code', self::COUNTRY)->pluck('id', 'code');
        $rows = [];
        $slugsPerProvince = [];

        foreach ($this->rows($file) as $row) {
            $provinceId = $provinceIds[$row['province_code']]
                ?? throw new RuntimeException("Unknown province code [{$row['province_code']}] for municipality {$row['code']}.");

            $slug = Str::slug($row['name']);

            // Two municipalities of the same province may share a slug: the INE code disambiguates.
            if (isset($slugsPerProvince[$provinceId][$slug])) {
                $slug .= '-'.$row['code'];
            }
            $slugsPerProvince[$provinceId][$slug] = true;

            $rows[] = [
                'province_id' => $provinceId,
                'country_code' => self::COUNTRY,
                'code' => $row['code'],
                'name' => $row['name'],
                'slug' => $slug,
                'latitude' => $row['latitude'] !== '' ? (float) $row['latitude'] : null,
                'longitude' => $row['longitude'] !== '' ? (float) $row['longitude'] : null,
                'population' => $row['population'] !== '' ? (int) $row['population'] : null,
            ];
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            Municipality::query()->upsert(
                $chunk,
                ['country_code', 'code'],
                ['province_id', 'name', 'slug', 'latitude', 'longitude', 'population'],
            );
        }
    }

    /**
     * @return iterable<int, array<string, string>>
     */
    private function rows(string $file): iterable
    {
        $handle = fopen($file, 'r');

        throw_if($handle === false, new RuntimeException("Cannot open {$file}."));

        $header = fgetcsv($handle, escape: '');

        throw_if($header === false, new RuntimeException("{$file} has no header row."));

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            /** @var array<string, string> $assoc */
            $assoc = array_combine($header, $row);

            yield $assoc;
        }

        fclose($handle);
    }
}
