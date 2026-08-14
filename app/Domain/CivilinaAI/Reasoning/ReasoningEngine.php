<?php

namespace App\Domain\CivilinaAI\Reasoning;

use App\Domain\CivilinaAI\DTO\DiagnosticInput;
use App\Domain\CivilinaAI\DTO\ReasoningResult;
use App\Domain\CivilinaAI\Enums\AnswerabilityStatus;
use App\Domain\CivilinaAI\Facts\FactExtractor;
use App\Domain\CivilinaAI\Questions\QuestionSelector;
use App\Domain\CivilinaAI\Recognition\ConceptRecognizer;
use App\Domain\CivilinaAI\Recommendations\RecommendationEngine;

final readonly class ReasoningEngine
{
    public const VERSION = '2.0.0';

    public function __construct(
        private ConceptRecognizer $recognizer,
        private FactExtractor $facts,
        private QuestionSelector $questions,
        private RecommendationEngine $recommendations,
    ) {
    }

    public function evaluate(DiagnosticInput $input): ReasoningResult
    {
        $recognition = $this->recognizer->recognize($input->freeText);
        $dimensions = $this->facts->extract($recognition, $input);
        $evidencePack = $this->facts->evidencePackFor($dimensions);
        $status = $this->answerability($dimensions, $evidencePack);
        $nextQuestion = $this->questions->select($dimensions, $input->answers);
        $recommendations = $this->recommendations->rank($dimensions);

        return new ReasoningResult([
            'reasoningCoreVersion' => self::VERSION,
            'interactionMode' => $dimensions['interactionMode'],
            'persona' => $dimensions['persona'],
            'scope' => $dimensions['scope'],
            'primaryDomain' => $dimensions['primaryDomain'],
            'relatedDomains' => $dimensions['relatedDomains'],
            'capabilities' => $dimensions['capabilities'],
            'controlDimensions' => $dimensions['controlDimensions'],
            'entities' => $dimensions['scope']['entities'],
            'symptoms' => $dimensions['symptoms'],
            'suspectedCauses' => $dimensions['suspectedCauses'],
            'confirmedCauses' => $dimensions['confirmedCauses'],
            'evidence' => $dimensions['evidence'],
            'uncertainties' => $dimensions['uncertainties'],
            'decisionNeeds' => $this->decisionNeeds($dimensions),
            'decisionHorizon' => $dimensions['decisionHorizon'],
            'answerabilityStatus' => $status->value,
            'recommendations' => $recommendations,
            'immediateRecommendations' => array_values(array_filter($recommendations, fn (array $item): bool => $item['priority'] === 'immediate')),
            'exploreRecommendations' => array_values(array_filter($recommendations, fn (array $item): bool => $item['priority'] === 'explore')),
            'nextBestQuestion' => $nextQuestion,
            'responseBlueprint' => $this->responseBlueprint($dimensions, $status, $nextQuestion, $evidencePack),
            'sensitiveIntent' => $dimensions['sensitiveIntent'],
            'peopleGuardrail' => $dimensions['peopleGuardrail'],
            'safetyCritical' => $dimensions['safetyCritical'],
            'externalVerificationRequired' => $dimensions['externalVerificationRequired'],
            'humanExpertRecommended' => in_array($status, [AnswerabilityStatus::HumanExpertRecommended, AnswerabilityStatus::ExternalVerificationRequired], true),
            'recognizedConcepts' => $dimensions['concepts'],
        ]);
    }

    private function answerability(array $dimensions, ?array $evidencePack): AnswerabilityStatus
    {
        if ($dimensions['externalVerificationRequired']) {
            return AnswerabilityStatus::ExternalVerificationRequired;
        }
        if ($dimensions['safetyCritical'] || $dimensions['contractInterpretation'] || $dimensions['peopleGuardrail']) {
            return AnswerabilityStatus::HumanExpertRecommended;
        }
        if (in_array($dimensions['interactionMode'], ['review', 'critique', 'audit'], true) && ! $dimensions['hasDocumentReference']) {
            return AnswerabilityStatus::NeedDocument;
        }
        if ($dimensions['interactionMode'] === 'calculate' && ! $dimensions['hasNumbers']) {
            return AnswerabilityStatus::NeedData;
        }
        if (in_array($dimensions['interactionMode'], ['create', 'design'], true)
            && (! $dimensions['hasDocumentReference'] || ! in_array('decision', $dimensions['controlDimensions'], true))) {
            return AnswerabilityStatus::NeedClarification;
        }
        if (array_filter($dimensions['uncertainties'], fn (array $item): bool => $item['blocking'])) {
            return AnswerabilityStatus::NeedClarification;
        }
        if ($evidencePack !== null && count($evidencePack['required']) > count($dimensions['evidence']) + 2) {
            return AnswerabilityStatus::NeedData;
        }
        if ($dimensions['primaryDomain'] === null) {
            return AnswerabilityStatus::OutOfScope;
        }

        return AnswerabilityStatus::CanAnswer;
    }

    private function expertLens(array $dimensions): array
    {
        $mapping = [
            'contractsClaims' => 'Contracts & Claims', 'hse' => 'HSE', 'quality' => 'QA/QC', 'procurement' => 'Procurement',
            'technicalDocumentManagement' => 'Engineering', 'planning' => 'Project Controls', 'costControl' => 'Project Controls',
            'projectControls' => 'Project Controls', 'resourcesProductivity' => 'Construction', 'dataReporting' => 'Data Governance',
            'processManagement' => 'Organisation', 'integrationAutomation' => 'Digital Transformation',
        ];

        return array_slice(array_values(array_unique(array_values(array_filter(array_map(fn (string $id): ?string => $mapping[$id] ?? null, $dimensions['capabilities']))))), 0, 3);
    }

    private function suggestedArtifact(array $dimensions): string
    {
        $mode = $dimensions['interactionMode'];
        if ($mode === 'audit') {
            return 'grille de revue et registre des écarts';
        }
        if ($mode === 'compare') {
            return 'matrice de comparaison avec critères et hypothèses';
        }
        if ($mode === 'prioritize') {
            return 'matrice de priorisation traçable';
        }
        if (in_array($mode, ['create', 'design'], true)) {
            return 'structure de livrable à valider avant production';
        }
        if (in_array('wbs', $dimensions['concepts'], true)) {
            return 'dictionnaire WBS et matrice WBS-zone-ouvrage';
        }
        if (in_array('evm', $dimensions['concepts'], true)) {
            return 'fiche EVM à date de statut avec hypothèses';
        }
        if (in_array('contractsClaims', $dimensions['concepts'], true)) {
            return 'chronologie factuelle et registre de preuves';
        }

        return 'note de décision structurée';
    }

    private function decisionNeeds(array $dimensions): array
    {
        $needs = [];
        if (in_array('decision', $dimensions['controlDimensions'], true)) {
            $needs[] = 'clarifier la décision et son responsable';
        }
        if ($dimensions['decisionHorizon'] !== 'unknown') {
            $needs[] = 'tenir compte de l’horizon '.$dimensions['decisionHorizon'];
        }
        if ($dimensions['uncertainties'] !== []) {
            $needs[] = 'distinguer les faits, hypothèses et informations manquantes';
        }
        if ($dimensions['peopleGuardrail']) {
            $needs[] = 'fonder la décision sur des critères professionnels vérifiables';
        }

        return $needs;
    }

    private function cautions(array $dimensions, AnswerabilityStatus $status): array
    {
        $items = [];
        if ($dimensions['sensitiveIntent']) {
            $items[] = 'Ne transmettez pas de données confidentielles dans cette démonstration locale.';
        }
        if ($dimensions['peopleGuardrail']) {
            $items[] = 'Le MBTI ne doit jamais être utilisé seul pour recruter, promouvoir ou affecter une personne.';
        }
        if ($dimensions['safetyCritical']) {
            $items[] = 'La maîtrise immédiate du danger prime sur toute analyse différée.';
        }
        if ($dimensions['contractInterpretation']) {
            $items[] = 'Une interprétation contractuelle engageante requiert le contrat, les preuves et un expert habilité.';
        }
        if ($status === AnswerabilityStatus::ExternalVerificationRequired) {
            $items[] = 'La version actuelle du référentiel ou de la réglementation doit être vérifiée auprès d’une source officielle.';
        }
        if ($dimensions['suspectedCauses'] !== []) {
            $items[] = 'Une cause suspectée ne doit pas être présentée comme un fait confirmé.';
        }

        return $items;
    }

    private function missingData(array $dimensions, ?array $evidencePack, AnswerabilityStatus $status): array
    {
        $missing = [];
        if ($status === AnswerabilityStatus::NeedDocument) {
            $missing[] = 'document source, version et critères de revue';
        }
        if ($status === AnswerabilityStatus::NeedData) {
            array_push($missing, ...($evidencePack['required'] ?? ['valeurs, unités, date de statut et hypothèses']));
        }
        foreach ($dimensions['uncertainties'] as $item) {
            $missing[] = $item['id'];
        }
        if ($dimensions['interactionMode'] === 'calculate' && ! $dimensions['hasNumbers']) {
            $missing[] = 'données chiffrées comparables';
        }

        return array_values(array_unique($missing));
    }

    private function responseBlueprint(array $dimensions, AnswerabilityStatus $status, ?array $nextQuestion, ?array $evidencePack): array
    {
        $canAnswerNow = $status === AnswerabilityStatus::CanAnswer;
        $sections = ['Contexte compris', 'Faits et preuves', 'Incertitudes'];
        array_push($sections, ...($canAnswerNow ? ['Lecture métier', 'Options et arbitrages', 'Prochaine action'] : ['Information nécessaire avant conclusion']));

        return [
            'canAnswerNow' => $canAnswerNow,
            'answerabilityStatus' => $status->value,
            'interactionMode' => $dimensions['interactionMode'],
            'objective' => $canAnswerNow ? 'Préparer une décision explicable sans dépasser les preuves disponibles.' : 'Réduire l’incertitude avant de produire une conclusion.',
            'mustAsk' => $nextQuestion['text'] ?? null,
            'missingData' => $this->missingData($dimensions, $evidencePack, $status),
            'answerSections' => $sections,
            'cautions' => $this->cautions($dimensions, $status),
            'suggestedArtifact' => $this->suggestedArtifact($dimensions),
            'expertLens' => $this->expertLens($dimensions),
        ];
    }
}
