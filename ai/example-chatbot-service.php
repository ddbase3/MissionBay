<?php declare(strict_types=1);

namespace Base3AgentsWebsite\Chatbot;

use Base3\Api\IOutput;
use Base3\Api\IRequest;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentMemoryFactory;
use MissionBay\Api\IAgentFlowFactory;

class ChatbotService implements IOutput {

	public function __construct(
		private readonly IRequest $request,
		private readonly IAgentContextFactory $agentcontextfactory,
		private readonly IAgentMemoryFactory $agentmemoryfactory,
		private readonly IAgentFlowFactory $agentflowfactory
	) {}

	public static function getName(): string {
		return 'chatbotservice';
	}

	public function getOutput($out = 'html'): string {
		$prompt = $this->request->post('prompt');

		if (!$prompt) {
			return 'Please provide a prompt.';
		}

		$memory = $this->agentmemoryfactory->createMemory('sessionmemory');
		$context = $this->agentcontextfactory->createContext('agentcontext', $memory);

		$system = 'You are an extremely sarcastic conversation partner with a dry and profound sense of humor.';

		$json = <<<JSON
{
  "nodes": [
    {
      "id": "cfg",
      "type": "getconfigurationnode",
      "inputs": {
        "section": "openaiconversation",
        "key": "apikey"
      }
    },
    {
      "id": "ai",
      "type": "simpleopenainode",
      "inputs": {
        "model": "gpt-3.5-turbo"
      }
    },
    {
      "id": "log",
      "type": "loggernode",
      "inputs": {
        "scope": "development"
      }
    },
    {
      "id": "msg",
      "type": "staticmessagenode"
    }
  ],
  "connections": [
    { "from": "cfg", "output": "value", "to": "ai", "input": "apikey" },
    { "from": "__input__", "output": "system", "to": "ai", "input": "system" },
    { "from": "__input__", "output": "prompt", "to": "ai", "input": "prompt" },
    { "from": "ai", "output": "response", "to": "log", "input": "message" },
    { "from": "ai", "output": "response", "to": "msg", "input": "text" }
  ]
}
JSON;

		$data = json_decode($json, true);
		$flow = $this->agentflowfactory->createFromArray('strictflow', $data, $context);
		$outputs = $flow->run(['system' => $system, 'prompt' => $prompt]);

		foreach ($outputs as $output) {
			if (!isset($output['message'])) continue;
			return $output['message'];
		}

		return 'Error: ' . json_encode($outputs);
	}

	public function getHelp(): string {
		return 'ChatbotService – receives a user prompt, queries OpenAI, and returns the response.';
	}
}

