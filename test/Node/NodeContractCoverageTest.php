<?php declare(strict_types=1);

namespace MissionBay\Test\Node;

use Base3\Configuration\Api\IConfiguration;
use Base3\Logger\Api\ILogger;
use MissionBay\Node\AbstractAgentNode;
use MissionBay\Node\Ai\AiEmbedTextNode;
use MissionBay\Node\Ai\DeepLTranslateNode;
use MissionBay\Node\Ai\OpenAiResponseNode;
use MissionBay\Node\Ai\SimpleLlamaNode;
use MissionBay\Node\Ai\SimpleOpenAiNode;
use MissionBay\Node\Core\GetConfigurationNode;
use MissionBay\Node\Core\GetContextVarNode;
use MissionBay\Node\Core\SetContextVarNode;
use MissionBay\Node\Core\TestInputNode;
use MissionBay\Node\Data\ArrayGetNode;
use MissionBay\Node\Data\ArraySetNode;
use MissionBay\Node\Data\JsonToArrayNode;
use MissionBay\Node\Data\TryArrayGetNode;
use MissionBay\Node\Http\HttpGetNode;
use MissionBay\Node\Http\HttpRequestNode;
use MissionBay\Node\Message\LoggerNode;
use MissionBay\Node\Message\StaticMessageNode;
use MissionBay\Node\Message\StringReverserNode;
use MissionBay\Node\Message\TelegramSendMessage;
use PHPUnit\Framework\TestCase;

final class NodeContractCoverageTest extends TestCase {

	public function testAllBasicNodeTypesHaveUniqueDiscoverableNames(): void {
		$names = [];
		foreach ($this->nodes() as $node) {
			$name = $node::getName();
			$this->assertSame(strtolower($name), $name);
			$this->assertSame(1, preg_match('/^[a-z][a-z0-9]*$/', $name));
			$this->assertFalse(in_array($name, $names, true));
			$names[] = $name;
		}
		$this->assertCount(19, $names);
	}

	public function testAllBasicNodeTypesHaveHumanReadableDescriptions(): void {
		foreach ($this->nodes() as $node) {
			$this->assertNotEmpty(trim($node->getDescription()), $node::getName());
		}
	}

	public function testInputAndOutputDefinitionsContainNoRepeatedPortNames(): void {
		foreach ($this->nodes() as $node) {
			$inputs = array_map(static fn($port): string => $port->name, $node->getInputDefinitions());
			$outputs = array_map(static fn($port): string => $port->name, $node->getOutputDefinitions());
			$this->assertSame(count($inputs), count(array_unique($inputs)), $node::getName() . ' input ports');
			$this->assertSame(count($outputs), count(array_unique($outputs)), $node::getName() . ' output ports');
			$this->assertNotEmpty($inputs, $node::getName());
			$this->assertNotEmpty($outputs, $node::getName());
		}
	}

	public function testCoreNodePortsRepresentTheExactContextOperations(): void {
		$expected = [
			'getconfigurationnode' => [['section', 'key'], ['value', 'error']],
			'getcontextvarnode' => [['key'], ['value', 'error']],
			'setcontextvarnode' => [['key', 'value'], ['success', 'error']],
			'testinputnode' => [['value'], ['value']]
		];
		$this->assertNodePorts($expected);
	}

	public function testDataNodePortsRetainArrayAndJsonContracts(): void {
		$expected = [
			'arraygetnode' => [['array', 'path'], ['value', 'error']],
			'arraysetnode' => [['array', 'path', 'value'], ['array', 'error']],
			'jsontoarraynode' => [['json'], ['array', 'error']],
			'tryarraygetnode' => [['array', 'path'], ['value']]
		];
		$this->assertNodePorts($expected);
	}

	public function testHttpNodePortsExposeResponseAndFailureSignals(): void {
		$this->assertNodePorts([
			'httpgetnode' => [['url', 'active'], ['body', 'error']],
			'httprequestnode' => [['url', 'method', 'headers', 'body', 'active'], ['status', 'body', 'error']]
		]);
	}

	public function testMessageNodePortsRetainTheirDeclaredOutputs(): void {
		$this->assertNodePorts([
			'loggernode' => [['scope', 'message'], ['logged', 'error']],
			'staticmessagenode' => [['text'], ['message']],
			'stringreversernode' => [['text'], ['reversed']],
			'telegramsendmessage' => [['bot_token', 'chat_id', 'message'], ['message_id', 'error']]
		]);
	}

	public function testAllNodesPreserveTheBaseIdAndConfigurationOperations(): void {
		foreach ($this->nodes() as $node) {
			$this->assertTrue($node instanceof AbstractAgentNode);
			$node->setId('contract-id');
			$node->setConfig(['required' => true]);
			$this->assertSame('contract-id', $node->getId());
			$this->assertSame(['required' => true], $node->getConfig());
		}
	}

	public function testResourceFreeNodesExposeNoUnexpectedDocks(): void {
		foreach ([new ArrayGetNode(), new ArraySetNode(), new GetContextVarNode(), new SetContextVarNode(), new TestInputNode(), new StaticMessageNode(), new HttpGetNode()] as $node) {
			$this->assertSame([], $node->getDockDefinitions(), $node::getName());
		}
	}

	private function assertNodePorts(array $expected): void {
		$nodes = [];
		foreach ($this->nodes() as $node) {
			$nodes[$node::getName()] = $node;
		}
		foreach ($expected as $name => $ports) {
			$node = $nodes[$name];
			$this->assertSame($ports[0], array_map(static fn($port): string => $port->name, $node->getInputDefinitions()), $name . ' inputs');
			$this->assertSame($ports[1], array_map(static fn($port): string => $port->name, $node->getOutputDefinitions()), $name . ' outputs');
		}
	}

	/** @return AbstractAgentNode[] */
	private function nodes(): array {
		return [
			new GetConfigurationNode($this->createStub(IConfiguration::class)),
			new GetContextVarNode(), new SetContextVarNode(), new TestInputNode(),
			new ArrayGetNode(), new ArraySetNode(), new JsonToArrayNode(), new TryArrayGetNode(),
			new HttpGetNode(), new HttpRequestNode(),
			new LoggerNode($this->createStub(ILogger::class)), new StaticMessageNode(),
			new StringReverserNode(), new TelegramSendMessage(),
			new AiEmbedTextNode(), new DeepLTranslateNode(), new OpenAiResponseNode(),
			new SimpleLlamaNode(), new SimpleOpenAiNode()
		];
	}
}
