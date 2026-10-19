<?php

namespace Tests;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\DbOci8Driver;
use ByJG\Util\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Runs without an Oracle server: nothing listens on 127.0.0.1:1, so oci_connect() fails right after
 * receiving the credentials, which is the part under test.
 */
#[RequiresPhpExtension('oci8')]
class DbOci8DriverTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function dataProviderUriWithoutCredentials(): array
    {
        return [
            'no user and no password' => ['oci8://127.0.0.1:1/XE'],
            'no password' => ['oci8://scott@127.0.0.1:1/XE'],
        ];
    }

    #[DataProvider('dataProviderUriWithoutCredentials')]
    public function testMissingCredentialsAreNotPassedAsNull(string $connection): void
    {
        $uri = new Uri($connection);
        $this->assertNull($uri->getPassword());

        $errors = [];
        set_error_handler(function (int $errno, string $errstr) use (&$errors): bool {
            $errors[] = [$errno, $errstr];
            return true; // swallow the oci_connect() connection warning
        });
        try {
            new DbOci8Driver($uri);
            $this->fail('The connection to 127.0.0.1:1 was expected to fail');
        } catch (DatabaseException) {
            // expected: nothing listens on that port
        } finally {
            restore_error_handler();
        }

        $deprecations = array_filter($errors, fn(array $error) => $error[0] === E_DEPRECATED);
        $this->assertSame([], array_values($deprecations));
    }
}
