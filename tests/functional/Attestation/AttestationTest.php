<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2019 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace Webauthn\Bundle\Tests\Functional\Attestation;

use Assert\InvalidArgumentException;
use Cose\Algorithms;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webauthn\AttestationStatement\AttestationStatement;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\Bundle\Service\PublicKeyCredentialCreationOptionsFactory;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\CertificateTrustPath;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * @group functional
 */
class AttestationTest extends KernelTestCase
{
    /**
     * @test
     */
    public function anAttestationResponseCanBeLoadedAndVerified(): void
    {
        self::bootKernel();

        $publicKeyCredentialCreationOptions = new PublicKeyCredentialCreationOptions(
            new PublicKeyCredentialRpEntity('My Application'),
            new PublicKeyCredentialUserEntity('test@foo.com', random_bytes(64), 'Test PublicKeyCredentialUserEntity'),
            base64_decode('9WqgpRIYvGMCUYiFT20o1U7hSD193k11zu4tKP7wRcrE26zs1zc4LHyPinvPGS86wu6bDvpwbt8Xp2bQ3VBRSQ==', true),
            [
                new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_ES256),
            ],
            60000,
            [],
            new AuthenticatorSelectionCriteria(),
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            new AuthenticationExtensionsClientInputs()
        );

        $publicKeyCredential = self::$kernel->getContainer()->get(PublicKeyCredentialLoader::class)->load('{"id":"mMihuIx9LukswxBOMjMHDf6EAONOy7qdWhaQQ7dOtViR2cVB_MNbZxURi2cvgSvKSILb3mISe9lPNG9sYgojuY5iNinYOg6hRVxmm0VssuNG2pm1-RIuTF9DUtEJZEEK","type":"public-key","rawId":"mMihuIx9LukswxBOMjMHDf6EAONOy7qdWhaQQ7dOtViR2cVB/MNbZxURi2cvgSvKSILb3mISe9lPNG9sYgojuY5iNinYOg6hRVxmm0VssuNG2pm1+RIuTF9DUtEJZEEK","response":{"clientDataJSON":"eyJjaGFsbGVuZ2UiOiI5V3FncFJJWXZHTUNVWWlGVDIwbzFVN2hTRDE5M2sxMXp1NHRLUDd3UmNyRTI2enMxemM0TEh5UGludlBHUzg2d3U2YkR2cHdidDhYcDJiUTNWQlJTUSIsImNsaWVudEV4dGVuc2lvbnMiOnt9LCJoYXNoQWxnb3JpdGhtIjoiU0hBLTI1NiIsIm9yaWdpbiI6Imh0dHBzOi8vbG9jYWxob3N0Ojg0NDMiLCJ0eXBlIjoid2ViYXV0aG4uY3JlYXRlIn0=","attestationObject":"o2NmbXRkbm9uZWdhdHRTdG10oGhhdXRoRGF0YVjkSZYN5YgOjGh0NBcPZHZgW4/krrmihjLHmVzzuoMdl2NBAAAAAAAAAAAAAAAAAAAAAAAAAAAAYJjIobiMfS7pLMMQTjIzBw3+hADjTsu6nVoWkEO3TrVYkdnFQfzDW2cVEYtnL4ErykiC295iEnvZTzRvbGIKI7mOYjYp2DoOoUVcZptFbLLjRtqZtfkSLkxfQ1LRCWRBCqUBAgMmIAEhWCAcPxwKyHADVjTgTsat4R/Jax6PWte50A8ZasMm4w6RxCJYILt0FCiGwC6rBrh3ySNy0yiUjZpNGAhW+aM9YYyYnUTJ"}}');

        $descriptor = $publicKeyCredential->getPublicKeyCredentialDescriptor();
        static::assertEquals(PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY, $descriptor->getType());
        static::assertEquals(base64_decode('mMihuIx9LukswxBOMjMHDf6EAONOy7qdWhaQQ7dOtViR2cVB/MNbZxURi2cvgSvKSILb3mISe9lPNG9sYgojuY5iNinYOg6hRVxmm0VssuNG2pm1+RIuTF9DUtEJZEEK', true), $descriptor->getId());
        static::assertEquals([], $descriptor->getTransports());

        $response = $publicKeyCredential->getResponse();
        static::assertInstanceOf(AuthenticatorAttestationResponse::class, $response);
        static::assertEquals(AttestationStatement::TYPE_NONE, $response->getAttestationObject()->getAttStmt()->getType());
        static::assertInstanceOf(EmptyTrustPath::class, $response->getAttestationObject()->getAttStmt()->getTrustPath());

        $uri = $this->prophesize(UriInterface::class);
        $uri->getHost()->willReturn('localhost');
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn($uri->reveal());

        self::$kernel->getContainer()->get(AuthenticatorAttestationResponseValidator::class)->check(
            $publicKeyCredential->getResponse(),
            $publicKeyCredentialCreationOptions,
            $request->reveal()
        );
    }

    /**
     * @test
     */
    public function aFullCertificateChainShouldNotBeUsedForThisSelfAttestation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid attestation statement. The attestation type is not allowed for this authenticator');

        self::bootKernel();
        $options = '{"status":"ok","errorMessage":"","rp":{"name":"Webauthn Demo","id":"webauthn.spomky-labs.com"},"pubKeyCredParams":[{"type":"public-key","alg":-8},{"type":"public-key","alg":-7},{"type":"public-key","alg":-43},{"type":"public-key","alg":-35},{"type":"public-key","alg":-36},{"type":"public-key","alg":-257},{"type":"public-key","alg":-258},{"type":"public-key","alg":-259},{"type":"public-key","alg":-37},{"type":"public-key","alg":-38},{"type":"public-key","alg":-39}],"challenge":"h8lQZpu-S0rTLOOeAr7BeWoPPTkhtqcEzlHizEyzVeQ","attestation":"direct","user":{"name":"fwOcfew16ujF_p7Hl5eh","id":"ZTc4N2YzZmItMDgwZS00ZDNjLTlhZDItYmE3OTAwYTVlNTg1","displayName":"Gretchen Mo"},"authenticatorSelection":{"requireResidentKey":false,"userVerification":"preferred"},"timeout":60000}';
        $result = '{"id":"icUOVRPT8oO9WQhpaE90z6jlKCac8JdnczpH6t694JQ","rawId":"icUOVRPT8oO9WQhpaE90z6jlKCac8JdnczpH6t694JQ","response":{"attestationObject":"o2NmbXRmcGFja2VkZ2F0dFN0bXSjY2FsZyZjc2lnWEYwRAIgZrOe5oDaahYFZM1iH9P_NJpbwN1FY0swi0d8pGImrwYCIBggQ17iKyGOnqVYumhwL_escFlB27AETQl0yLO8nmuWY3g1Y4FZBEUwggRBMIICKaADAgECAgEBMA0GCSqGSIb3DQEBCwUAMIGhMRgwFgYDVQQDDA9GSURPMiBURVNUIFJPT1QxMTAvBgkqhkiG9w0BCQEWImNvbmZvcm1hbmNlLXRvb2xzQGZpZG9hbGxpYW5jZS5vcmcxFjAUBgNVBAoMDUZJRE8gQWxsaWFuY2UxDDAKBgNVBAsMA0NXRzELMAkGA1UEBhMCVVMxCzAJBgNVBAgMAk1ZMRIwEAYDVQQHDAlXYWtlZmllbGQwHhcNMTgwNTIzMTQzOTQzWhcNMjgwNTIwMTQzOTQzWjCBwjEjMCEGA1UEAwwaRklETzIgQkFUQ0ggS0VZIHByaW1lMjU2djExMTAvBgkqhkiG9w0BCQEWImNvbmZvcm1hbmNlLXRvb2xzQGZpZG9hbGxpYW5jZS5vcmcxFjAUBgNVBAoMDUZJRE8gQWxsaWFuY2UxIjAgBgNVBAsMGUF1dGhlbnRpY2F0b3IgQXR0ZXN0YXRpb24xCzAJBgNVBAYTAlVTMQswCQYDVQQIDAJNWTESMBAGA1UEBwwJV2FrZWZpZWxkMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAETzpeXqtsH7yul_bfZEmWdix773IAQCp2xvIw9lVvF6qZm1l_xL9Qiq-OnvDNAT9aub0nkUvwgEN4y8yxG4m1RqMsMCowCQYDVR0TBAIwADAdBgNVHQ4EFgQUVk33wPjGVbahH2xNGfO_QeL9AXkwDQYJKoZIhvcNAQELBQADggIBAIfdoJ_4IIkF0S1Bzgmk6dR6XAYbDsPGcEyiQccGCvMnEOf0EVwXDEYvGsVXsR9h6FA04P7vg5Lx9lGBmI1_0QMYBiIeHT4Kyl8FZ3bTMIiOUJ0MFzKHCrc8snrkkL-iDcJP0AriS-SzgMj7TVFjE2_1LwnHWFo7WWBTnmEEivU_-nbVkqelwISE-MH9wgWscmovmIkZ9534teeL1K6rbg4eenjgyu_iHs4PZ6W7nJZ918Vv5EYbZNhREUgZgaKOyKLT3fDRkwE58FL7der8Osd5ltmus2RjjnmAkJnl5Xzc2u30n39QXRVkeX-HCdIBQL9ve03-XRmUL2Q9w3MkPTiXid0UEPYp19DYcZNfunJtYtnvIfYEze6LY6mJpxo7N3s4T3WsdgHa5nJDuN2DbnIX0zxAj00cz-K0KN0G8Bi3hAJPx1fqCZmIgZHYX9hdkCzJu0nXqmdSY4NVtbzSU9vPL49RBhfv2il4P27owGivOv2DTwSWlvUXcOBJ3xVIuWxHZA-WUqXgBwkMwg59kc5AY7Nq0xXuKkRVFrQvkWeMBakce9I1yyMPgK6XnraY7cyUjakLKj5RL6cjMbldmY567gNv8rD90Q86jbO0fCVTSoontEQGxu3reN1C2XAu6IsfCSmLCesA5l_Bssu71jPi0vV4mVB9-7BL8CiWzPscaGF1dGhEYXRhWKSWBOqCgk6YpK2hS0Ri0Nc6jsRpEw2pGxkwdFkin3SjWUEAAABTKy7LtFm0RPqGjaBySF2K4AAgicUOVRPT8oO9WQhpaE90z6jlKCac8JdnczpH6t694JSlAQIDJiABIVgg_YaefIjxYBFvUYvXdQCxl-2AbCSIAOCwxt_m_qQ-SeEiWCBiSYa6JD1eR7jbJOppgUdyIle1hmviAK-UvU7_-SZbCg","clientDataJSON":"eyJvcmlnaW4iOiJodHRwczovL3dlYmF1dGhuLnNwb21reS1sYWJzLmNvbSIsImNoYWxsZW5nZSI6Img4bFFacHUtUzByVExPT2VBcjdCZVdvUFBUa2h0cWNFemxIaXpFeXpWZVEiLCJ0eXBlIjoid2ViYXV0aG4uY3JlYXRlIn0"},"type":"public-key"}';

        $publicKeyCredentialCreationOptions = PublicKeyCredentialCreationOptions::createFromString($options);
        $publicKeyCredential = self::$kernel->getContainer()->get(PublicKeyCredentialLoader::class)->load($result);

        $descriptor = $publicKeyCredential->getPublicKeyCredentialDescriptor();
        static::assertEquals(PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY, $descriptor->getType());
        static::assertEquals(hex2bin('89c50e5513d3f283bd590869684f74cfa8e528269cf09767733a47eadebde094'), $descriptor->getId());
        static::assertEquals([], $descriptor->getTransports());

        $response = $publicKeyCredential->getResponse();
        static::assertInstanceOf(AuthenticatorAttestationResponse::class, $response);
        static::assertEquals(AttestationStatement::TYPE_BASIC, $response->getAttestationObject()->getAttStmt()->getType());
        static::assertInstanceOf(CertificateTrustPath::class, $response->getAttestationObject()->getAttStmt()->getTrustPath());

        $uri = $this->prophesize(UriInterface::class);
        $uri->getHost()->willReturn('webauthn.spomky-labs.com');
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn($uri->reveal());

        self::$kernel->getContainer()->get(AuthenticatorAttestationResponseValidator::class)->check(
            $publicKeyCredential->getResponse(),
            $publicKeyCredentialCreationOptions,
            $request->reveal()
        );
        static::assertTrue(false);
    }

    /**
     * @test
     */
    public function eddsa(): void
    {
        self::bootKernel();

        $options = '{"status":"ok","errorMessage":"","rp":{"name":"Webauthn Demo","id":"webauthn.spomky-labs.com"},"pubKeyCredParams":[{"type":"public-key","alg":-8},{"type":"public-key","alg":-7},{"type":"public-key","alg":-43},{"type":"public-key","alg":-35},{"type":"public-key","alg":-36},{"type":"public-key","alg":-257},{"type":"public-key","alg":-258},{"type":"public-key","alg":-259},{"type":"public-key","alg":-37},{"type":"public-key","alg":-38},{"type":"public-key","alg":-39}],"challenge":"EhNVt3T8V12FJvSAc50nhKnZ-MEc-kf84xepDcGyN1g","attestation":"direct","user":{"name":"XY5nn3p_6olTLjoB2Jbb","id":"OTI5ZmJhMmYtMjM2MS00YmM2LWE5MTctYmI3NmFhMTRjN2Y5","displayName":"Bennie Moneypenny"},"authenticatorSelection":{"requireResidentKey":false,"userVerification":"preferred"},"timeout":60000}';
        $result = '{"id":"WT7a99M1zA3XUBBvEwXqPzP0C3zNoS_SpmMpv2sG2YM","rawId":"WT7a99M1zA3XUBBvEwXqPzP0C3zNoS_SpmMpv2sG2YM","response":{"attestationObject":"o2NmbXRmcGFja2VkZ2F0dFN0bXSiY2FsZydjc2lnWECRl1RciDxSF7hkhJbqVJeryUIFrX7r6QQMQq8bIP4wYRA6f96iOO4wiOo34l65kZ5v1erxSmIaH56VySUSMusEaGF1dGhEYXRhWIGWBOqCgk6YpK2hS0Ri0Nc6jsRpEw2pGxkwdFkin3SjWUEAAAAykd_q15WeRHWtJpsNSCvgiQAgWT7a99M1zA3XUBBvEwXqPzP0C3zNoS_SpmMpv2sG2YOkAQEDJyAGIVgg4smTlXUJnAP_RqNWNv2Eqkh8I7ZDS0IuSgotbPygd9k","clientDataJSON":"eyJvcmlnaW4iOiJodHRwczovL3dlYmF1dGhuLnNwb21reS1sYWJzLmNvbSIsImNoYWxsZW5nZSI6IkVoTlZ0M1Q4VjEyRkp2U0FjNTBuaEtuWi1NRWMta2Y4NHhlcERjR3lOMWciLCJ0eXBlIjoid2ViYXV0aG4uY3JlYXRlIn0"},"type":"public-key"}';

        $publicKeyCredentialCreationOptions = PublicKeyCredentialCreationOptions::createFromString($options);
        $publicKeyCredential = self::$kernel->getContainer()->get(PublicKeyCredentialLoader::class)->load($result);

        $descriptor = $publicKeyCredential->getPublicKeyCredentialDescriptor();
        static::assertEquals(PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY, $descriptor->getType());
        static::assertEquals(hex2bin('593edaf7d335cc0dd750106f1305ea3f33f40b7ccda12fd2a66329bf6b06d983'), $descriptor->getId());
        static::assertEquals([], $descriptor->getTransports());

        $response = $publicKeyCredential->getResponse();
        static::assertInstanceOf(AuthenticatorAttestationResponse::class, $response);
        static::assertEquals(AttestationStatement::TYPE_SELF, $response->getAttestationObject()->getAttStmt()->getType());
        static::assertInstanceOf(EmptyTrustPath::class, $response->getAttestationObject()->getAttStmt()->getTrustPath());

        $uri = $this->prophesize(UriInterface::class);
        $uri->getHost()->willReturn('webauthn.spomky-labs.com');
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn($uri->reveal());

        self::$kernel->getContainer()->get(AuthenticatorAttestationResponseValidator::class)->check(
            $publicKeyCredential->getResponse(),
            $publicKeyCredentialCreationOptions,
            $request->reveal()
        );
    }

    /**
     * @test
     */
    public function aPublicKeyCredentialCreationOptionsCanBeCreatedFromProfile(): void
    {
        self::bootKernel();

        /** @var PublicKeyCredentialCreationOptionsFactory $factory */
        $factory = self::$kernel->getContainer()->get(PublicKeyCredentialCreationOptionsFactory::class);
        $options = $factory->create(
            'default',
            new PublicKeyCredentialUserEntity('test@foo.com', random_bytes(64), 'Test PublicKeyCredentialUserEntity')
        );

        static::assertEquals(32, mb_strlen($options->getChallenge(), '8bit'));
        static::assertInstanceOf(AuthenticationExtensionsClientInputs::class, $options->getExtensions());
        static::assertEquals([], $options->getExcludeCredentials());
        static::assertEquals(11, \count($options->getPubKeyCredParams()));
        static::assertEquals('none', $options->getAttestation());
        static::assertEquals(60000, $options->getTimeout());
        static::assertInstanceOf(PublicKeyCredentialRpEntity::class, $options->getRp());
        static::assertInstanceOf(PublicKeyCredentialUserEntity::class, $options->getUser());
        static::assertInstanceOf(AuthenticatorSelectionCriteria::class, $options->getAuthenticatorSelection());
    }
}
