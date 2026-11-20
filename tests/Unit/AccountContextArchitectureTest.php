<?php

namespace Tests\Unit;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class AccountContextArchitectureTest extends TestCase
{
    public function test_request_and_guard_account_reads_go_through_account_context(): void
    {
        $parser = (new ParserFactory)->createForHostVersion();
        $finder = new NodeFinder;
        $offenders = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../app'));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $nodes = $parser->parse((string) file_get_contents($file->getPathname()));
            $functions = $finder->findInstanceOf($nodes ?? [], FunctionLike::class);

            foreach ($functions as $function) {
                $body = $function->getStmts() ?? [];
                $aliases = [];

                foreach ($finder->findInstanceOf($body, Expr\Assign::class) as $assignment) {
                    if ($assignment->var instanceof Expr\Variable && is_string($assignment->var->name)) {
                        $aliases[$assignment->var->name] = self::isRequestUser($assignment->expr);
                    }
                }

                $reads = $finder->find($body, fn (Node $node) => self::isDirectAccountRead($node, $aliases));
                foreach ($reads as $read) {
                    $offenders[] = $file->getFilename().':'.$read->getStartLine();
                }
            }
        }

        self::assertSame([], $offenders, 'Use AccountContext ao resolver a conta do usuário da requisição.');
    }

    /** @param array<string, bool> $aliases */
    private static function isDirectAccountRead(Node $node, array $aliases): bool
    {
        if (
            ! $node instanceof Expr\PropertyFetch
            && ! $node instanceof Expr\NullsafePropertyFetch
            && ! $node instanceof Expr\MethodCall
            && ! $node instanceof Expr\NullsafeMethodCall
        ) {
            return false;
        }

        return $node->name instanceof Identifier
            && in_array($node->name->toString(), ['account_id', 'account'], true)
            && (self::isRequestUser($node->var) || (
                $node->var instanceof Expr\Variable
                && is_string($node->var->name)
                && ($aliases[$node->var->name] ?? false)
            ));
    }

    private static function isRequestUser(Expr $expr): bool
    {
        if (
            ! $expr instanceof Expr\MethodCall
            && ! $expr instanceof Expr\NullsafeMethodCall
            && ! $expr instanceof Expr\StaticCall
        ) {
            return false;
        }

        if (! $expr->name instanceof Identifier || $expr->name->toString() !== 'user') {
            return false;
        }

        if ($expr instanceof Expr\StaticCall) {
            return $expr->class instanceof Name && $expr->class->getLast() === 'Auth';
        }

        return ($expr->var instanceof Expr\Variable && $expr->var->name === 'request')
            || ($expr->var instanceof Expr\FuncCall
                && $expr->var->name instanceof Name
                && in_array($expr->var->name->toString(), ['auth', 'request'], true));
    }
}
