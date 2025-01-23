<?php

declare(strict_types=1);

namespace R3H6\OpentelemetryAutoDoctrineDbal;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Result;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;

use function OpenTelemetry\Instrumentation\hook;

class DoctrineDbalInstrumentation
{
    public const NAME = 'doctrine-dbal';

    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation(
            'io.opentelemetry.contrib.php.' . self::NAME,
        );

        hook(
            Connection::class,
            'prepare',
            pre: static function (Connection $connection, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                $span = self::start($instrumentation, $class, $function, $filename, $lineno);
                $span->setAttribute(TraceAttributes::DB_QUERY_TEXT, mb_convert_encoding($params[0] ?? 'undefined', 'UTF-8'));
                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            post: static function (Connection $connection, array $params, mixed $return, ?\Throwable $exception) {
                self::end($exception);
            }
        );

        hook(
            Connection::class,
            'query',
            pre: static function (Connection $connection, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                $span = self::start($instrumentation, $class, $function, $filename, $lineno);
                $span->setAttribute(TraceAttributes::DB_QUERY_TEXT, mb_convert_encoding($params[0] ?? 'undefined', 'UTF-8'));
                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            post: static function (Connection $connection, array $params, mixed $return, ?\Throwable $exception) {
                self::end($exception);
            }
        );

        hook(
            Connection::class,
            'exec',
            pre: static function (Connection $connection, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                $span = self::start($instrumentation, $class, $function, $filename, $lineno);
                $span->setAttribute(TraceAttributes::DB_QUERY_TEXT, mb_convert_encoding($params[0] ?? 'undefined', 'UTF-8'));
                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            post: static function (Connection $connection, array $params, mixed $return, ?\Throwable $exception) {
                self::end($exception);
            }
        );

        hook(
            Connection::class,
            'beginTransaction',
            pre: static function (Connection $connection, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                $span = self::start($instrumentation, $class, $function, $filename, $lineno);
                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            post: static function (Connection $connection, array $params, mixed $return, ?\Throwable $exception) {
                self::end($exception);
            }
        );

        hook(
            Connection::class,
            'commit',
            pre: static function (Connection $connection, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                $span = self::start($instrumentation, $class, $function, $filename, $lineno);
                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            post: static function (Connection $connection, array $params, mixed $return, ?\Throwable $exception) {
                self::end($exception);
            }
        );

        hook(
            Connection::class,
            'rollBack',
            pre: static function (Connection $connection, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                $span = self::start($instrumentation, $class, $function, $filename, $lineno);
                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            post: static function (Connection $connection, array $params, mixed $return, ?\Throwable $exception) {
                self::end($exception);
            }
        );

        $methods = [
            'fetchNumeric',
            'fetchAssociative',
            'fetchOne',
            'fetchAllNumeric',
            'fetchAllAssociative',
            'fetchFirstColumn',
            'rowCount',
            'columnCount',
            'free',
        ];

        foreach ($methods as $method) {
            hook(
                Result::class,
                $method,
                pre: static function (Result $result, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                    $span = self::start($instrumentation, $class, $function, $filename, $lineno);
                    Context::storage()->attach($span->storeInContext(Context::getCurrent()));
                },
                post: static function (Result $result, array $params, mixed $return, ?\Throwable $exception) {
                    self::end($exception);
                }
            );
        }
    }

    private static function start(CachedInstrumentation $instrumentation, string $class, string $function, ?string $filename, ?int $lineno): SpanInterface
    {
        return $instrumentation->tracer()->spanBuilder(sprintf('%s:%s', $class, $function))
            ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
            ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
            ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
            ->setAttribute(TraceAttributes::CODE_LINENO, $lineno)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();
    }

    private static function end(?\Throwable $exception): void
    {
        $scope = Context::storage()->scope();
        if (!$scope) {
            return;
        }
        $scope->detach();
        $span = Span::fromContext($scope->context());
        if ($exception) {
            $span->recordException($exception, [TraceAttributes::EXCEPTION_ESCAPED => true]);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        }
        $span->end();
    }
}
