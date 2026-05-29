<?php


use App\ValueObjects\TenantId;
use App\ValueObjects\UserId;

describe('TenantId', function (): void {

    it('creates with valid positive integer', function (): void {
        $id = new TenantId(1);
        expect($id->value)->toBe(1);
    });

    it('throws on zero', function (): void {
        expect(fn () => new TenantId(0))->toThrow(\InvalidArgumentException::class);
    });

    it('throws on negative', function (): void {
        expect(fn () => new TenantId(-5))->toThrow(\InvalidArgumentException::class);
    });

    it('compares equality', function (): void {
        $a = new TenantId(1);
        $b = new TenantId(1);
        $c = new TenantId(2);

        expect($a->equals($b))->toBeTrue()
            ->and($a->equals($c))->toBeFalse();
    });

    it('casts to string', function (): void {
        expect((string) new TenantId(42))->toBe('42');
    });
});

describe('UserId', function (): void {

    it('creates with valid positive integer', function (): void {
        $id = new UserId(15);
        expect($id->value)->toBe(15);
    });

    it('throws on zero', function (): void {
        expect(fn () => new UserId(0))->toThrow(\InvalidArgumentException::class);
    });

    it('compares equality', function (): void {
        $a = new UserId(10);
        $b = new UserId(10);
        $c = new UserId(20);

        expect($a->equals($b))->toBeTrue()
            ->and($a->equals($c))->toBeFalse();
    });
});
