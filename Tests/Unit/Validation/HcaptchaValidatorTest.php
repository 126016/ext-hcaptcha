<?php

declare(strict_types=1);

/*
 * This file is part of the hcaptcha extension for TYPO3
 * - (c) 2021 waldhacker UG (haftungsbeschränkt)
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Waldhacker\Hcaptcha\Tests\Unit\Validation;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Waldhacker\Hcaptcha\Service\ConfigurationService;
use Waldhacker\Hcaptcha\Validation\HcaptchaValidator;

/**
 * @backupGlobals enabled
 * @coversDefaultClass \Waldhacker\Hcaptcha\Validation\HcaptchaValidator
 */
class HcaptchaValidatorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ServerRequestInterface|ObjectProphecy
     */
    private $typo3request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->typo3request = $this->prophesize(ServerRequestInterface::class);
        $GLOBALS['TYPO3_REQUEST'] = $this->typo3request->reveal();
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * @test
     * @covers ::validate
     * @covers ::isValid
     * @covers ::validateHcaptcha
     */
    public function validateReturnsErrorIfPostResponseFieldIsEmpty(): void
    {
        $hcaptchaValidator = $this
            ->getMockBuilder(HcaptchaValidator::class)
            ->onlyMethods(['translateErrorMessage'])
            ->getMock();
        $result = $hcaptchaValidator->validate(1);
        $errors = $result->getErrors();
        self::assertCount(1, $errors);
        self::assertSame(1566209403, $errors[0]->getCode());
    }

    /**
     * @test
     * @covers ::validate
     * @covers ::isValid
     * @covers ::validateHcaptcha
     * @covers ::getConfigurationService
     * @covers ::getRequestFactory
     */
    public function validateReturnsErrorIfVerificationRequestReturnsError(): void
    {
        $hcaptchaValidator = $this
            ->getMockBuilder(HcaptchaValidator::class)
            ->onlyMethods(['translateErrorMessage'])
            ->getMock();

        $normalizedParams = $this->prophesize(NormalizedParams::class);
        $normalizedParams->getRemoteAddress()->willReturn('127.0.0.1');

        $this->typo3request->getParsedBody()
            ->willReturn(['h-captcha-response' => 'verification-key-response']);
        $this->typo3request->getAttribute('normalizedParams')->willReturn($normalizedParams->reveal());

        $configurationService = $this->prophesize(ConfigurationService::class);
        $configurationService->getVerificationServer()->willReturn('https://example.com/siteverify');
        $configurationService->getPrivateKey()->willReturn('my_superb_key');
        GeneralUtility::addInstance(ConfigurationService::class, $configurationService->reveal());

        $requestFactory = $this->prophesize(RequestFactory::class);
        $responseBody = json_encode(['success' => false, 'error-codes' => ['invalid-input-secret']]);
        $requestFactory->request(Argument::cetera())->willReturn(new Response(200, [], $responseBody));
        GeneralUtility::addInstance(RequestFactory::class, $requestFactory->reveal());

        $result = $hcaptchaValidator->validate(1);

        $requestFactory->request('https://example.com/siteverify?secret=my_superb_key&response=verification-key-response&remoteip=127.0.0.1', 'POST')->shouldHaveBeenCalled();
        $errors = $result->getErrors();
        self::assertCount(1, $errors);
        self::assertSame(1566209403, $errors[0]->getCode());
    }
}
