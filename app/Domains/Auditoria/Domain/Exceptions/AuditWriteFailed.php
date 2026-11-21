<?php

namespace App\Domains\Auditoria\Domain\Exceptions;

// Falha na gravação obrigatória do histórico, sem o SQL e os valores alterados que a exceção do banco levaria ao log.
final class AuditWriteFailed extends \RuntimeException {}
