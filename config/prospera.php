<?php

return [
    /*
     * E09: liberação de novas associações de uma identidade existente a outras contas (convites).
     *
     * A resolução da conta pela URL, a seleção de conta e a autorização funcionam com qualquer valor.
     * Desligada, a criação de convites para identidades existentes responde como antes da E09 e convites
     * pendentes não podem ser aceitos; vínculos e permissões existentes não mudam.
     */
    'multiple_accounts' => (bool) env('PROSPERA_MULTIPLE_ACCOUNTS', false),

    // Validade do convite para uma identidade existente.
    'invitation_days' => (int) env('PROSPERA_INVITATION_DAYS', 7),

    // S05: fuso do "hoje" das lotações até a S07 criar o fuso por estabelecimento (PD2-08).
    'business_timezone' => env('PROSPERA_BUSINESS_TIMEZONE', 'America/Sao_Paulo'),
];
