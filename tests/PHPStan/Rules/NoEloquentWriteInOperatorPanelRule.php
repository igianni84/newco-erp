<?php

declare(strict_types=1);

namespace Tests\PHPStan\Rules;

use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Operator-console write discipline (ADR 2026-06-19; design L4): a console class must never
 * mutate an Eloquent model directly — every write routes through the owning module's domain
 * action (`app(<Action>::class)->handle(...)`), which fires the FSM guards, domain events and
 * the `actor_role` audit envelope. The console binds models read-only for display; it never
 * writes them. This rule fails the build (`type_check`) on any such write, making the boundary
 * CI-enforced rather than convention.
 *
 * It flags a call to one of the Eloquent persistence / mass-assignment methods on a receiver
 * whose static type is an Eloquent {@see Model}, in any file whose path matches one of the
 * configured needles. Production scope is the console surface
 * (`app/Modules/OperatorPanel/Filament/`); the auth principal's own writes
 * (`Operator::save()` under `Models/`, the shipped operator-auth-foundation) are a separate,
 * legitimate concern and deliberately out of scope. Reads (`$model` binding, `Model::query()`
 * for display) are allowed.
 *
 * @implements Rule<CallLike>
 */
final class NoEloquentWriteInOperatorPanelRule implements Rule
{
    /**
     * Eloquent persistence + mass-assignment writes (design L4). Read methods are not listed.
     *
     * @var list<string>
     */
    private const FORBIDDEN_METHODS = [
        'save', 'saveQuietly', 'update', 'updateQuietly',
        'delete', 'forceDelete', 'create', 'insert', 'fill', 'setAttribute',
    ];

    /**
     * @param  list<string>  $scopedPathNeedles  path substrings that put an analysed file in
     *                                           scope (e.g. 'Modules/OperatorPanel/Filament/').
     */
    public function __construct(private readonly array $scopedPathNeedles) {}

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /**
     * @param  CallLike  $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->inScope($scope->getFile())) {
            return [];
        }

        // [method name, receiver type] for the two call shapes we police, or null for anything
        // else (dynamic method name, a New_/FuncCall under CallLike, ...).
        $resolved = null;

        if ($node instanceof MethodCall) {
            if ($node->name instanceof Identifier) {
                $resolved = [$node->name->toString(), $scope->getType($node->var)];
            }
        } elseif ($node instanceof StaticCall) {
            if ($node->name instanceof Identifier) {
                $resolved = [
                    $node->name->toString(),
                    $node->class instanceof Name
                        ? new ObjectType($scope->resolveName($node->class))
                        : $scope->getType($node->class),
                ];
            }
        }

        if ($resolved === null) {
            return [];
        }

        [$method, $receiverType] = $resolved;

        if (! in_array($method, self::FORBIDDEN_METHODS, true)) {
            return [];
        }

        if (! (new ObjectType(Model::class))->isSuperTypeOf($receiverType)->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'OperatorPanel console must not call %s() to write an Eloquent model — route the write through the owning module\'s domain action (app(<Action>::class)->handle(...)); the console reads models, it never writes them (ADR 2026-06-19).',
                $method,
            ))
                ->identifier('operatorPanel.noEloquentWrite')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function inScope(string $file): bool
    {
        $normalised = str_replace('\\', '/', $file);

        foreach ($this->scopedPathNeedles as $needle) {
            if (str_contains($normalised, $needle)) {
                return true;
            }
        }

        return false;
    }
}
