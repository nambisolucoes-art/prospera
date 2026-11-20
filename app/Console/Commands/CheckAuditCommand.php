<?php

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
        $errors = Schema::hasTable('audit_records') ? [] : ['A tabela central de auditoria não está disponível. Execute as migrações.'];
        $checked = 0;
        foreach (File::allFiles(app_path()) as $file) {
            $path = $file->getPathname();
            if ($file->getExtension() !== 'php' || (! str_contains($path, '/Domain/Models/') && ! str_contains($path, '/app/Models/'))) {
                continue;
            }
            $class = 'App\\'.str_replace('/', '\\', substr($path, strlen(app_path()) + 1, -4));
            if (! class_exists($class) || ! is_subclass_of($class, Model::class) || (new \ReflectionClass($class))->isAbstract()) {
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
            $missing = array_diff(Schema::getColumnListing($model->getTable()), ['id', 'created_at', 'updated_at'], $fields, array_keys($ignored));
            foreach ($missing as $field) {
                $errors[] = $class.': campo sem cobertura: '.$field;
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
