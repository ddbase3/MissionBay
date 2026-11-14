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

namespace Base3\MissionBay\Test\Node\Ai;

use Base3\AssistantFoundation\Api\IAgentMemory;
use Base3\MissionBay\Context\AgentContext;
use PHPUnit\Framework\TestCase;

final class OpenAiResponseNodeConversationTest extends TestCase {

	public function testExistingHistoryIsSentAsRoleContentOnly(): void {
		$history = [
			['role' => 'user', 'content' => 'first', 'private_data' => 'do not forward'],
			['role' => 'assistant', 'content' => 'reply', 'feedback' => 'excellent'],
			['role' => 'user'],
			['content' => 'missing role']
		];
		$memory = $this->createStub(IAgentMemory::class);
		$memory->method('loadNodeHistory')->willReturn($history);
		$node = new OpenAiResponseNodeProbe('reply-node');
		$result = $node->execute(['messages' => [['from' => 'user@example.test', 'body' => 'question', 'subject' => 'Subject']], 'apikey' => 'fake-key'], [], new AgentContext($memory));
		$this->assertSame('Generated answer', $result['responses'][0]['body']);
		$this->assertSame('user@example.test', $result['responses'][0]['to']);
		$this->assertSame('Re: Subject', $result['responses'][0]['subject']);
		$this->assertSame([
			['role' => 'system', 'content' => 'You are a helpful assistant.'],
			['role' => 'user', 'content' => 'first'],
			['role' => 'assistant', 'content' => 'reply'],
			['role' => 'user', 'content' => 'question']
		], $node->requests[0]['messages']);
	}

	public function testApiKeyAndSelectedModelAreForwardedWithoutPersistence(): void {
		$appended = [];
		$memory = $this->memory([], $appended);
		$node = new OpenAiResponseNodeProbe('reply-node');
		$result = $node->execute(['messages' => [['body' => 'text']], 'model' => 'configured-model', 'apikey' => 'temporary-key'], [], new AgentContext($memory));
		$this->assertSame('configured-model', $node->requests[0]['model']);
		$this->assertSame('temporary-key', $node->requests[0]['api_key']);
		$this->assertSame(2, count($appended));
		$this->assertFalse(str_contains(json_encode($appended), 'temporary-key'));
		$this->assertSame('Generated answer', $result['responses'][0]['body']);
	}

	public function testDefaultModelIsAppliedWhenNotSpecified(): void {
		$node = new OpenAiResponseNodeProbe('reply-node');
		$node->execute(['messages' => [['body' => 'hello']], 'apikey' => 'fake'], [], new AgentContext($this->memory()));
		$this->assertSame('gpt-3.5-turbo', $node->requests[0]['model']);
	}

	public function testOneInputMessagePersistsBothSidesOfConversation(): void {
		$messages = [];
		$memory = $this->memory([], $messages);
		$node = new OpenAiResponseNodeProbe('reply-node');
		$node->reply = 'Assistant response';
		$node->execute(['messages' => [['body' => 'User question']], 'apikey' => 'fake'], [], new AgentContext($memory));
		$this->assertCount(2, $messages);
		$this->assertSame('reply-node', $messages[0]['node']);
		$this->assertSame('user', $messages[0]['message']['role']);
		$this->assertSame('User question', $messages[0]['message']['content']);
		$this->assertSame('assistant', $messages[1]['message']['role']);
		$this->assertSame('Assistant response', $messages[1]['message']['content']);
		$this->assertNull($messages[1]['message']['feedback']);
		$this->assertStringContainsString('msg_', $messages[1]['message']['id']);
		$this->assertNotEmpty($messages[1]['message']['timestamp']);
	}

	public function testMultipleMessagesRemainOrderedAndUseIndividualRecipients(): void {
		$appended = [];
		$memory = $this->memory([], $appended);
		$node = new OpenAiResponseNodeProbe('reply-node');
		$result = $node->execute(['messages' => [
			['body' => 'First', 'from' => 'a@example.test', 'subject' => 'A'],
			['body' => 'Second', 'from' => 'b@example.test', 'subject' => 'B']
		], 'apikey' => 'fake'], [], new AgentContext($memory));
		$this->assertCount(2, $node->requests);
		$this->assertSame('First', $node->requests[0]['messages'][1]['content']);
		$this->assertSame('Second', $node->requests[1]['messages'][1]['content']);
		$this->assertSame('a@example.test', $result['responses'][0]['to']);
		$this->assertSame('b@example.test', $result['responses'][1]['to']);
		$this->assertSame('Re: B', $result['responses'][1]['subject']);
		$this->assertCount(4, $appended);
	}

	public function testEmptyMessageListDoesNotMakeAnApiCallOrWriteMemory(): void {
		$appended = [];
		$node = new OpenAiResponseNodeProbe('reply-node');
		$result = $node->execute(['messages' => [], 'apikey' => 'fake'], [], new AgentContext($this->memory([], $appended)));
		$this->assertSame(['responses' => []], $result);
		$this->assertSame([], $node->requests);
		$this->assertSame([], $appended);
	}

	public function testInputMessagesWithoutSubjectAndSenderHaveDefaults(): void {
		$node = new OpenAiResponseNodeProbe('reply-node');
		$result = $node->execute(['messages' => [['body' => 'Question']], 'apikey' => 'fake'], [], new AgentContext($this->memory()));
		$this->assertSame(['to' => '', 'subject' => 'Re: ', 'body' => 'Generated answer'], $result['responses'][0]);
	}

	public function testPromptBuilderRetainsOnlyCompleteRoleContentPairs(): void {
		$node = new OpenAiResponseNodeProbe();
		$this->assertSame("User: hello\nAssistant: world\nUser: next\nBot:", $node->renderPrompt([
			['role' => 'user', 'content' => 'hello'],
			['role' => 'assistant', 'content' => 'world'],
			['role' => 'missing'],
			['content' => 'without role'],
			'malformed'
		], 'next'));
	}

	public function testPromptBuilderAcceptsEmptyHistory(): void {
		$this->assertSame("User: question\nBot:", (new OpenAiResponseNodeProbe())->renderPrompt([], 'question'));
	}

	/** @param array<int,array<string,mixed>> $history */
	private function memory(array $history = [], ?array &$appended = null): IAgentMemory {
		$memory = $this->createStub(IAgentMemory::class);
		$memory->method('loadNodeHistory')->willReturn($history);
		$memory->method('appendNodeHistory')->willReturnCallback(static function(string $nodeId, array $message) use (&$appended): void {
			$appended[] = ['node' => $nodeId, 'message' => $message];
		});
		return $memory;
	}
}

require_once __DIR__ . '/OpenAiResponseNodeProbe.php';
