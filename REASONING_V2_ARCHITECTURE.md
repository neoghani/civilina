# Reasoning Core 2.0 Architecture

## Contract

The JavaScript Reasoning Core V2 is the reference contract. The Golden Dataset V2 contains 180 deterministic cases and is mirrored into the PHP test fixture.

The engine separates:

- business domains: Executive Control, Projects, Organisation, Management Systems, Digitalisation and People;
- capabilities: Project Controls, Planning, Cost Control, Risk, Change, Contracts & Claims, Engineering, Technical Documents, Procurement, Quality, HSE, Construction, Resources, Document Control, Processes, Data & Reporting, Integration & Automation;
- interaction modes, personas, business scopes, entities, control dimensions, symptoms, causes, evidence and decision horizons.

## Pipeline

1. `civilina-ai-recognizer.js` recognizes concepts without deciding.
2. `civilina-ai-facts-v2.js` builds typed dimensions and an evidence pack.
3. `civilina-ai-questions-v2.js` selects the question with the highest decision value.
4. `civilina-ai-reasoning-v2.js` determines answerability and delegates ranking.
5. Recommendations are limited to three immediate and two exploratory items; every item requires evidence and a justification.
6. `civilina-ai-runtime.js` preserves the validated V1 journey contract while exposing Core 2.0.

## Safety

- symptoms never become confirmed causes without evidence;
- unknown causes remain explicit;
- People decisions are human decisions and cannot be automated from MBTI or one indicator;
- sensitive intent requires both an intent to transmit and a sensitive business object;
- free text is not persisted, logged or sent to the server.

