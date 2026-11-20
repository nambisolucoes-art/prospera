<?php

namespace Tests\Unit;

use App\Support\TaxDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxDocumentTest extends TestCase
{
    public function test_numeric_cnpj_is_normalized_formatted_and_split(): void
    {
        $document = TaxDocument::cnpj(' 11.222.333/0001-81 ');

        self::assertNotNull($document);
        self::assertSame('11222333000181', $document->value());
        self::assertSame(TaxDocument::CNPJ, $document->type());
        self::assertSame('11222333', $document->root());
        self::assertSame('0001', $document->order());
        self::assertSame('11.222.333/0001-81', $document->formatted());
    }

    public function test_alphanumeric_cnpj_follows_the_receita_example(): void
    {
        // Exemplo publicado pela Receita Federal para a IN RFB 2.229/2024.
        $document = TaxDocument::cnpj('12.abc.345/01de-35');

        self::assertNotNull($document);
        self::assertSame('12ABC34501DE35', $document->value());
        self::assertSame('12ABC345', $document->root());
        self::assertSame('01DE', $document->order());
        self::assertSame('12.ABC.345/01DE-35', $document->formatted());
        self::assertNull(TaxDocument::cnpj('12ABC34501DE36'));
        self::assertNull(TaxDocument::cnpj('12ABC34501DEA5'));
    }

    public function test_cpf_is_validated_and_formatted(): void
    {
        $document = TaxDocument::cpf('529.982.247-25');

        self::assertNotNull($document);
        self::assertTrue($document->isCpf());
        self::assertNull($document->root());
        self::assertNull($document->order());
        self::assertSame('529.982.247-25', $document->formatted());
    }

    /** @return array<string, array{string}> */
    public static function invalidCnpjs(): array
    {
        return [
            'dígito errado' => ['11.222.333/0001-82'],
            'sequência repetida' => ['00.000.000/0000-00'],
            'letras repetidas' => ['AAAAAAAAAAAA00'],
            'tamanho curto' => ['11.222.333/0001'],
            'dígito verificador com letra' => ['11222333000A81'],
            'caractere inválido' => ['11.222.333/0001-8!'],
        ];
    }

    #[DataProvider('invalidCnpjs')]
    public function test_invalid_cnpjs_are_rejected(string $value): void
    {
        self::assertNull(TaxDocument::cnpj($value));
    }

    /** @return array<string, array{string}> */
    public static function invalidCpfs(): array
    {
        return [
            'dígito errado' => ['529.982.247-24'],
            'sequência repetida' => ['111.111.111-11'],
            'letras' => ['52998224A25'],
            'tamanho' => ['5299822472'],
        ];
    }

    #[DataProvider('invalidCpfs')]
    public function test_invalid_cpfs_are_rejected(string $value): void
    {
        self::assertNull(TaxDocument::cpf($value));
    }

    public function test_root_accepts_the_root_or_a_full_valid_cnpj(): void
    {
        self::assertSame('11222333', TaxDocument::cnpjRoot('11.222.333')?->value());
        self::assertSame('11222333', TaxDocument::cnpjRoot('11.222.333/0002-62')?->value());
        self::assertSame('12ABC345', TaxDocument::cnpjRoot('12abc345')?->value());
        self::assertSame('11.222.333', TaxDocument::cnpjRoot('11222333')?->formatted());
        self::assertNull(TaxDocument::cnpjRoot('11.222.333/0002-63'));
        self::assertNull(TaxDocument::cnpjRoot('1122233'));
    }

    public function test_legal_entity_comparison_uses_the_root_and_never_the_order(): void
    {
        $headquarters = TaxDocument::cnpj('11.222.333/0001-81');
        $branch = TaxDocument::cnpj('11.222.333/0002-62');
        $other = TaxDocument::cnpj('99.888.777/0001-00');
        $root = TaxDocument::cnpjRoot('11222333');
        $alphanumeric = TaxDocument::cnpj('12ABC34501DE35');
        $alphanumericBranch = TaxDocument::cnpj('12ABC34502DE06');

        self::assertNotNull($headquarters);
        self::assertNotNull($branch);
        self::assertNotNull($other);
        self::assertNotNull($root);
        self::assertNotNull($alphanumeric);
        self::assertNotNull($alphanumericBranch);

        self::assertTrue($headquarters->isSameLegalEntityAs($branch));
        self::assertTrue($root->isSameLegalEntityAs($branch));
        self::assertTrue($alphanumeric->isSameLegalEntityAs($alphanumericBranch));
        self::assertFalse($headquarters->isSameLegalEntityAs($other));
        self::assertFalse($alphanumeric->isSameLegalEntityAs($headquarters));
    }

    public function test_cpf_is_only_the_same_legal_entity_as_the_same_cpf(): void
    {
        $cpf = TaxDocument::cpf('52998224725');
        $sameCpf = TaxDocument::cpf('529.982.247-25');
        $otherCpf = TaxDocument::cpf('11144477735');
        $cnpj = TaxDocument::cnpj('11222333000181');

        self::assertNotNull($cpf);
        self::assertNotNull($sameCpf);
        self::assertNotNull($otherCpf);
        self::assertNotNull($cnpj);

        self::assertTrue($cpf->isSameLegalEntityAs($sameCpf));
        self::assertFalse($cpf->isSameLegalEntityAs($otherCpf));
        self::assertFalse($cpf->isSameLegalEntityAs($cnpj));
    }

    public function test_normalization_removes_masks_and_uppercases(): void
    {
        self::assertSame('12ABC34501DE35', TaxDocument::normalize(' 12.abc.345/01de-35 '));
        self::assertSame('52998224725', TaxDocument::normalize('529.982.247-25'));
        self::assertNull(TaxDocument::cnpj(null));
        self::assertNull(TaxDocument::cpf(null));
    }
}
