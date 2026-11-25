<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Auditoria\Domain\Contracts\Auditable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

final class CheckAuditCommand extends Command
{
    protected $signature = 'audit:check';

    protected $description = 'Verifica cobertura de modelos e campos de negócio na auditoria';

    public function handle(): int
    {
        $errors = [];
        $checked = 0;

        if (! Schema::hasTable('audit_records')) {
            $errors[] = 'A tabela central de auditoria não está disponível. Execute as migrações.';
        }

        foreach (File::allFiles(app_path()) as $file) {
            $path = $file->getPathname();
            $isModelFile = str_contains($path, '/Domain/Models/') || str_contains($path, '/app/Models/');

            if ($file->getExtension() !== 'php' || ! $isModelFile) {
                continue;
            }

            $class = 'App\\'.str_replace('/', '\\', substr($path, strlen(app_path()) + 1, -4));

            if (
                ! class_exists($class)
                || ! is_subclass_of($class, Model::class)
                || (new \ReflectionClass($class))->isAbstract()
            ) {
                continue;
            }

            $excluded = config('audit.excluded_models', []);

            if (isset($excluded[$class]) && is_string($excluded[$class]) && trim($excluded[$class]) !== '') {
                continue;
            }
            if (! is_subclass_of($class, Auditable::class)) {
                $errors[] = $class.': falta implementar Auditable.';

                continue;
            }
            $model = new $class;
            $checked++;
            if (! Schema::hasTable($model->getTable())) {
                $errors[] = $class.': execute as migrações.';

                continue;
            }
            $fields = array_map(fn ($field) => explode('.', $field)[0], array_keys($class::auditedFields()));
            $ignored = $class::unauditedFields();
            foreach ($ignored as $field => $reason) {
                if (trim($reason) === '') {
                    $errors[] = $class.': justifique a exclusão de '.$field;
                }
            }
            $missing = array_diff(
                Schema::getColumnListing($model->getTable()),
                ['id', 'created_at', 'updated_at'],
                $fields,
                array_keys($ignored),
            );

            foreach ($missing as $field) {
                $errors[] = $class.': campo sem cobertura: '.$field;
            }

            // A partir da E04, eventos de empresa e de unidade gravam os ids organizacionais (DA-12).
            $columns = Schema::getColumnListing($model->getTable());
            $probe = (new $class)->forceFill(['id' => 303]);
            $expectedCompany = null;
            $expectedEstablishment = null;

            if (in_array('company_id', $columns, true)) {
                $probe->forceFill(['company_id' => 101]);
                $expectedCompany = 101;
            }

            if (in_array('establishment_id', $columns, true)) {
                $probe->forceFill(['establishment_id' => 202]);
                $expectedEstablishment = 202;
            }

            if ($model->getTable() === 'companies') {
                $expectedCompany = 303;
            }

            if ($model->getTable() === 'establishments') {
                $expectedEstablishment = 303;
            }

            if ($expectedCompany !== null && $probe->auditCompanyId() !== $expectedCompany) {
                $errors[] = $class.': o evento não informa company_id.';
            }

            if ($expectedEstablishment !== null && $probe->auditEstablishmentId() !== $expectedEstablishment) {
                $errors[] = $class.': o evento não informa establishment_id.';
            }
        }
        foreach ($errors as $error) {
            $this->error($error);
        }
        if ($errors) {
            return self::FAILURE;
        }
        $this->info("Auditoria validada: {$checked} modelos de negócio com campos cobertos.");

        return self::SUCCESS;
    }
}
