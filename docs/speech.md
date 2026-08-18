# MissionBay Speech Services

## Purpose

This document explains how MissionBay implements the AssistantFoundation speech contracts through configured provider services.

## Foundation contracts

Consumers use:

```text
ISpeechToTextService
IRealtimeSpeechToTextSessionService
ITextToSpeechService
ITextToSpeechStream
```

MissionBay registers configured implementations for the first three interfaces.

## Settings groups

```text
service-stt
service-tts
```

Both use the normal `connection` settings group and service-driver architecture.

## Speech-to-text

`ConfiguredSpeechToTextService` resolves the requested STT preset through `ConfiguredServiceRuntimeResolver` and delegates transcription to the selected provider driver.

Current drivers:

```text
mistralspeechtotextdriver
openaispeechtotextdriver
```

## Realtime STT

`ConfiguredRealtimeSpeechToTextSessionService` provides the configured-session boundary for realtime transcription where the selected provider driver supports that mode.

Realtime behavior belongs to the speech contract and provider driver. It is not implemented as an agent-specific polling loop.

## Text-to-speech

`ConfiguredTextToSpeechService` resolves a TTS preset and delegates synthesis to the selected provider driver.

Current drivers:

```text
mistraltexttospeechdriver
openaitexttospeechdriver
```

Streaming output uses the AssistantFoundation stream contract when supported.

## Provider configuration

Provider endpoints, credentials and transport settings belong to `connection`. Model/voice/language/service options belong to the corresponding service preset.

This separation allows multiple STT or TTS presets to share one provider connection without copying secrets.

## Administration

MissionBay provides:

```text
speechtotextconfigdisplay
texttospeechconfigdisplay
```

These displays use the same configured-service definitions consumed by runtime code.
