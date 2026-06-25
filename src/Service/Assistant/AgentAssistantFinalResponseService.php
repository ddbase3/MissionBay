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

namespace MissionBay\Service\Assistant;

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentAssistantFinalResponseService;
use MissionBay\Api\IAgentAssistantMessageFactory;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;

final class AgentAssistantFinalResponseService implements IAgentAssistantFinalResponseService {

	public function __construct(private IAgentAssistantMessageFactory $messageFactory) {
	}

	public function createDirectResponse(IAiChatModel $model, AgentAssistantTurnResult $turnResult): string {
		if (!$turnResult->isCompleted()) {
			return $turnResult->getFallbackContent() ?? '';
		}

		return $this->messageFactory->normalizeContent($model->chat($turnResult->getMessages()));
	}

	public function createStreamingResponse(IAiChatModel $model, AgentAssistantTurnResult $turnResult, callable $onData, ?callable $onMeta = null): string {
		if (!$turnResult->isCompleted()) {
			$content = $turnResult->getFallbackContent() ?? '';
			if ($content !== '') {
				$onData($content);
			}

			return $content;
		}

		$finalContent = '';
		$metaCallback = $onMeta ?? function (array $meta): void {};

		$model->stream(
			$turnResult->getMessages(),
			[],
			function (string $delta) use (&$finalContent, $onData) {
				$finalContent .= $delta;
				$onData($delta);
			},
			$metaCallback
		);

		return $finalContent;
	}

	public function createAssistantMessage(AgentAssistantTurnResult $turnResult, string $content): array {
		return $this->messageFactory->createAssistantMessage($turnResult->getAssistantMessageId(), $content);
	}
}
