# Image Generation Services

## Purpose

MissionBay exposes configured image generation through the same connection, class-map, provider result, event, and usage-log infrastructure used by other AI operations.

Built-in image drivers:

```text
openai-image
openai-compatible-image
mistral-image
```

The provider-neutral runtime contract is:

```text
AssistantFoundation\Api\IImageGenerationModel
```

The generic discoverable driver contract is:

```text
AssistantFoundation\Api\IServiceDriverDefinition
```

## Architecture

```text
service-image settings
  -> connection id
  -> existing connection settings
  -> IServiceDriverDefinition
     -> implementation interface
     -> implementation getName() value
     -> service option schema
  -> IImageGenerationModel
  -> IAiProvider transport
  -> AiImageResult
  -> AiProviderRequestCompletedEvent
  -> MissionBayAiUsageLogListener
  -> base3_missionbay_ai_usage
```

Image drivers use the same generic definition contract as chat, embedding, search, parser, vector-store, and speech drivers. There is no image-specific driver registry or definition interface.

## Strict configuration ownership

Connection configuration is the only owner of:

```text
base URL
authentication type
authentication header
API key / secret resolver
default request timeout
connection-specific options
```

Image service configuration stores only:

```text
service id and name
connection reference
image driver
model
generation-specific options
optional per-service request and connect timeouts
enabled state
```

`ImageConfigDisplay` does not edit or duplicate connection endpoint or authentication values. It only selects an existing connection. Connection-owned keys are rejected in advanced image options and removed from loaded service option records.

The connection timeout is the default request timeout. An image service may override it with `timeoutSeconds` because image generation can have very different latency characteristics from other services using the same connection. `connectTimeoutSeconds` controls only connection establishment and defaults to 15 seconds when omitted.

## Mistral connection

Create the connection through the normal Connection administration.

Example values:

```text
ID:                 mistral_api
Name:               Mistral API
Type:               http
Driver:             http
Base URL:           https://api.mistral.ai
Authentication:     bearer
Secret mode:        env
Environment name:   MISTRAL_API_KEY
Timeout seconds:    120
Enabled:            yes
```

The corresponding connection record is maintained by the existing connection configuration. It is not part of the image service record.

## Mistral image service

Create the image service after the connection exists:

```text
ID:          mistral_course_images
Name:        Mistral Course Images
Connection:  mistral_api
Driver:      mistral-image
Model:       mistral-small-latest
Tool choice: required
Enabled:     yes
```

Equivalent service settings:

```php
$settingsStore->set('service-image', 'mistral_course_images', [
	'id' => 'mistral_course_images',
	'name' => 'Mistral Course Images',
	'serviceType' => 'image',
	'connection' => 'mistral_api',
	'driver' => 'mistral-image',
	'model' => 'mistral-small-latest',
	'enabled' => true,
	'options' => [
		'toolChoice' => 'required',
		'timeoutSeconds' => 90,
		'connectTimeoutSeconds' => 15
	]
]);

$settingsStore->save();
```

No endpoint, authentication, secret, or API key is stored in this record.

## Mistral API request

`MistralImageModel` uses the Mistral Chat Completions endpoint:

```text
POST /v1/chat/completions
```

Payload shape:

```json
{
  "model": "mistral-small-latest",
  "messages": [
    {
      "role": "user",
      "content": "Generate a professional course preview image ..."
    }
  ],
  "tools": [
    {
      "type": "image_generation"
    }
  ],
  "tool_choice": "required"
}
```

Mistral documents `image_generation` as a built-in Chat Completions tool. Tool responses use `choice.messages` and contain generated image URLs in response content.

References:

```text
https://docs.mistral.ai/api/endpoint/chat
https://docs.mistral.ai/resources/cookbooks/mistral-connectors-05-connectors-in-completions
```

## OpenAI image service

OpenAI continues to use:

```text
POST /v1/images/generations
```

Example image service:

```php
$settingsStore->set('service-image', 'openai_course_images', [
	'id' => 'openai_course_images',
	'name' => 'OpenAI Course Images',
	'serviceType' => 'image',
	'connection' => 'openai_api',
	'driver' => 'openai-image',
	'model' => 'gpt-image-2',
	'enabled' => true,
	'options' => [
		'size' => '1024x1024',
		'quality' => 'auto',
		'outputFormat' => 'png',
		'background' => 'auto',
		'moderation' => 'auto',
		'numberOfImages' => 1
	]
]);
```

The selected `openai_api` connection owns its endpoint and credential.

OpenAI-compatible services use driver `openai-compatible-image` and an existing provider-specific connection.

## Runtime usage

```php
$imageModelResource->setConfig([
	'service' => 'mistral_course_images'
]);

$result = $imageModelResource->generateResult(
	'Photorealistic editorial photograph of a professional caregiver having a friendly conversation with an elderly person in a bright modern care facility. Natural daylight, trustworthy atmosphere, no text, no logos, horizontal composition.'
);

$imageUrl = $result->getImages()[0]['url'] ?? '';
```

Mistral returns a hosted image URL. Applications requiring durable ownership should download and store the image through their normal file or media storage service.

## Shared usage logging

Every successful image generation returns `AiImageResult` metadata and dispatches the same `AiProviderRequestCompletedEvent` used by other AI operations.

No image-specific usage table exists. Image and LLM usage are written to:

```text
base3_missionbay_ai_usage
```

Normalized token columns:

```text
input_tokens
output_tokens
total_tokens
cached_input_tokens
reasoning_tokens
```

Provider-specific numeric usage values remain available in `metrics_json`. Image operations also include:

```text
input_prompts
output_images
```

The operation value is:

```text
image
```

OpenAI image usage is normalized from its image response `usage` object. Mistral usage is normalized from `prompt_tokens`, `completion_tokens`, and `total_tokens`. Missing values from compatible providers are not estimated.

## Adding another image provider

A specialty plugin should:

1. implement `AssistantFoundation\Api\IImageGenerationModel`;
2. implement `AssistantFoundation\Api\IServiceDriverDefinition`;
3. return `IImageGenerationModel::class` from `getImplementationInterface()`;
4. return the adapter's stable `getName()` value from `getImplementationName()`;
5. expose only model and generation-specific fields in `getConfigSchema()`;
6. reuse an existing connection or provide a separate `IConnectionDriverDefinition` when a genuinely new connection protocol is required;
7. normalize provider results through `AiImageResult` and the existing AI request event.

Do not add provider branches to `ConfiguredImageModelAgentResource` or `ImageConfigDisplay`.
