<?php

declare(strict_types=1);

namespace App\Domains\Auditoria\Application\Queries;

use App\Domains\Auditoria\Domain\Models\AuditRecord;

final class AuditValueFormatter
{
    /** @return array<int,array{label:string,before:mixed,after:mixed}> */
    public function changes(AuditRecord $record): array
    {
        $changes = [];
        foreach ($record->changes as $field => $change) {
            $changes[] = [
                'label' => $change['label'],
                'before' => $this->value($record->entity_type, $field, $change['before']),
                'after' => $this->value($record->entity_type, $field, $change['after']),
            ];
        }

        return $changes;
    }

    private function value(string $entity, string $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $phoneFields = [
            'phone',
            'whatsapp',
            'additional_phones',
            'details.phone',
            'details.whatsapp',
            'details.additional_phones',
        ];

        if (in_array($field, $phoneFields, true)) {
            $format = static fn (mixed $phone): mixed => is_string($phone) && preg_match('/^\d{10,11}$/', $phone)
                ? preg_replace('/^(\d{2})(\d{4,5})(\d{4})$/', '($1) $2-$3', $phone)
                : $phone;

            return is_array($value) ? array_map($format, $value) : $format($value);
        }

        // Rótulos das opções declarados por tipo e campo em config/audit.php, sem consultar os domínios.
        $labels = config('audit.values.'.$entity.'.'.$field, []);

        if (is_string($value) && isset($labels[$value])) {
            return $labels[$value];
        }
        if (is_bool($value)) {
            return match ($field) {
                // Pontos ficam no histórico anterior à E04; estabelecimentos usam o masculino.
                'active' => in_array($entity, ['point', 'establishment'], true)
                    ? ($value ? 'Ativo' : 'Inativo')
                    : ($value ? 'Ativa' : 'Inativa'),
                'access_enabled' => $value ? 'Habilitado' : 'Revogado',
                default => $value ? 'Sim' : 'Não',
            };
        }

        return $value;
    }
}
