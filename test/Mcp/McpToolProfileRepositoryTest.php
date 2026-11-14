<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace Base3\MissionBay\Test\Mcp;

use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use InvalidArgumentException;
use Base3\MissionBay\Mcp\McpToolProfileRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class McpToolProfileRepositoryTest extends TestCase {

	public function testProfileIdsAreNormalizedAndAuthoritative(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn(['id' => 'untrusted', 'type' => 'mcp', 'tools' => ['one']]);
		$repository = new McpToolProfileRepository($settings);
		$this->assertSame('profilename', $repository->getProfile(' PROFILE NAME! ')['id']);
		$this->assertSame('missionbay:mcp:profilename', $repository->getCredentialServiceId(' PROFILE NAME! '));
		$this->assertSame('mcptoolprofilerepository', McpToolProfileRepository::getName());
	}

	public function testEmptyAndUnknownProfilesAreRejected(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn([]);
		$repository = new McpToolProfileRepository($settings);
		foreach ([' !!! ', ''] as $id) {
			try {
				$repository->getProfile($id);
				$this->fail('Empty profile id was accepted.');
			} catch (InvalidArgumentException) {
				$this->addToAssertionCount(1);
			}
		}
		$this->expectException(RuntimeException::class);
		$repository->getProfile('missing');
	}

	public function testEnabledMcpProfilesAreFilteredWithoutEnablingOthers(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturn([
			' Zeta ' => ['type' => 'mcp', 'tools' => ['one']],
			'alpha' => ['type' => 'hybrid', 'enabled' => 'yes', 'tools' => []],
			'web' => ['type' => 'web', 'tools' => ['one']],
			'disabled' => ['type' => 'mcp', 'enabled' => false, 'tools' => ['one']],
			'optout' => ['type' => 'mcp', 'mcp_enabled' => 'off', 'tools' => ['one']],
			'missingtools' => ['type' => 'mcp'],
			'override' => ['type' => 'web', 'mcp_enabled' => 1, 'tools' => ['two']],
			'broken' => 'string'
		]);
		$repository = new McpToolProfileRepository($settings);
		$this->assertSame(['alpha', 'override', 'zeta'], array_keys($repository->getEnabledMcpProfiles()));
		$this->assertSame('zeta', $repository->getProfiles()['zeta']['id']);
	}

	public function testSingleEnabledProfileRequiresMcpToolsAndActivation(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn(['type' => 'mcp', 'enabled' => 'true', 'tools' => ['lookup']]);
		$repository = new McpToolProfileRepository($settings);
		$this->assertSame(['lookup'], $repository->getEnabledMcpProfile('mcp')['tools']);
	}

	public function testExposureAndCredentialFlagsAcceptExplicitBooleanValues(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$repository = new McpToolProfileRepository($settings);
		$this->assertTrue($repository->isFixedBearerEnabled(['token' => 'legacy-token']));
		$this->assertFalse($repository->isFixedBearerEnabled(['token' => 'legacy-token', 'mcp_fixed_bearer_enabled' => false]));
		$this->assertTrue($repository->isFixedBearerEnabled(['mcp_fixed_bearer_enabled' => 'yes']));
		$this->assertFalse($repository->isCredentialAccessEnabled([]));
		$this->assertFalse($repository->isCredentialAccessEnabled(['mcp_credential_enabled' => 'off']));
		$this->assertTrue($repository->isCredentialAccessEnabled(['mcp_credential_enabled' => '1']));
	}
}
