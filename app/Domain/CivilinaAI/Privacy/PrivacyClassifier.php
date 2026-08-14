<?php

namespace App\Domain\CivilinaAI\Privacy;

final class PrivacyClassifier
{
    public function normalize(string $text): string
    {
        $text = strtr(mb_strtolower($text), [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y',
            '’' => ' ', "'" => ' ',
        ]);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    public function detectsSensitiveIntent(string $text): bool
    {
        $value = $this->normalize($text);
        $share = '~\b(envoyer|envoi|partager|partage|joindre|uploader|upload|televerser|transmettre|transmission|deposer|importer|montrer|fournir|communiquer|mettre\s+a\s+disposition|vous\s+donner|vous\s+adresser)\b~';
        $negated = '~\b(?:sans|ne\s+(?:pas|plus|jamais)|ne\b.{0,20}\b(?:pas|plus|jamais)|pas\s+question\s+de)\b.{0,30}\b(?:envoyer|partager|joindre|uploader|televerser|transmettre|deposer|importer|fournir|communiquer)\b~';
        $sensitive = '~\b(contrats?|devis|factures?|budgets?|business\s+plans?|previsions?|forecasts?(?:\s+financiers?)?|p\s*&\s*l|comptes?\s+de\s+resultat|bilans?|cash[-\s]?flows?|donnees?\s+financieres?|finances?\s+internes?|paie|fiches?\s+de\s+paie|bulletins?\s+de\s+paie|salaires?|marges?|tresorerie|donnees?\s+rh|donnees?\s+personnelles?\s+nominatives?|procedures?\s+internes?|documents?\s+internes?|offres?\s+commerciales?|offres?\s+fournisseurs?|devis\s+fournisseurs?|prix(?:\s+confidentiels?)?|bordereaux?\s+de\s+prix|bpu|dqe|tarifs?\s+internes?|donnees?\s+clients?|listes?\s+clients?|clients?|donnees?\s+fournisseurs?|listes?\s+fournisseurs?|fournisseurs?|nda|documents?\s+confidentiels?)\b~';

        return ! preg_match($negated, $value) && preg_match($share, $value) && preg_match($sensitive, $value);
    }

    public function detectsPeopleDecision(string $text): bool
    {
        $value = $this->normalize($text);
        if (preg_match('~\bprofil\s+(?:de\s+|du\s+)?(?:risque|projet|cout|financier|tresorerie|marge|planning)\b~', $value)) {
            return false;
        }

        $asksForPerson = (bool) preg_match('~\b(qui|quel(?:le|s|les)?|lequel|laquelle|lesquels|lesquelles)\b~', $value);
        $hasDecision = (bool) preg_match('~\b(choisir|selectionner|nommer|affecter|promouvoir|recruter|designer|remplacer|mettre|confier|prendre\s+la\s+direction|prendre\s+la\s+responsabilite)\b~', $value);
        $hasRole = (bool) preg_match('~\b(manager|chef(?:s)?(?:\s+de\s+projet|\s+de\s+chantier|\s+d\s+equipe)?|responsable|directeur|superviseur|coordinateur|direction|responsabilite|poste|fonction)\b~', $value);
        $hasPerson = (bool) preg_match('~\b(candidats?|salaries?|employes?|collaborateurs?|personnes?|individus?|techniciens?|responsables?|chefs?|managers?|directeurs?|superviseurs?|coordinateurs?)\b~', $value);
        $explicitDirection = (bool) preg_match('~\bqui\b.{0,35}\bpeut\b.{0,25}\bprendre\s+la\s+(?:direction|responsabilite)\b~', $value);
        $ordered = (bool) preg_match('~\b(?:choisir|selectionner|nommer|affecter|promouvoir|recruter|designer|remplacer|mettre|confier)\b.{0,55}\b(?:candidat|salarie|employe|collaborateur|technicien|personne|manager|chef|responsable|directeur|superviseur|coordinateur)\b~', $value)
            || (bool) preg_match('~\b(?:candidat|salarie|employe|collaborateur|technicien|personne|manager|chef|responsable|directeur|superviseur|coordinateur)\b.{0,55}\b(?:choisir|selectionner|nommer|affecter|promouvoir|recruter|designer|remplacer|mettre|confier)\b~', $value);

        return $explicitDirection
            || ($asksForPerson && $hasDecision && $hasRole)
            || ($ordered && ($hasPerson || $hasRole) && ($asksForPerson || preg_match('~\b(meilleur|candidate?|salarie|collaborateur|technicien)\b~', $value)));
    }

    public function detectsPeopleSignal(string $text): bool
    {
        $value = $this->normalize($text);
        if ($this->detectsPeopleDecision($value)) {
            return true;
        }

        $nonPeople = preg_match('~\bprofil\s+(?:de\s+|du\s+)?(?:risque|projet|cout|financier|tresorerie|marge|planning)\b~', $value)
            || preg_match('~\bmanager\s+(?:un\s+|le\s+|mon\s+|notre\s+|des\s+)?(?:projet|programme|portefeuille|risque|planning)\b~', $value)
            || preg_match('~\b(?:management|gestion)\s+(?:du\s+|de\s+|des\s+)?(?:projet|programme|portefeuille|chantier|risque|planning)\b~', $value);
        $psychometric = (bool) preg_match('~\b(mbti|personnalite|psychometr|style\s+de\s+travail)\b~', $value);
        if ($nonPeople && ! $psychometric && ! preg_match('~\bequipe\b|\brecrut~', $value)) {
            return false;
        }

        return $psychometric
            || (bool) preg_match('~\b(recrutement|recruter|competences?|affectation|promotion|mobilite|equipe|ressources?\s+humaines?|rh|poste|fonction)\b~', $value)
            || (bool) preg_match('~\bprofil\s+(?:candidat|professionnel|de\s+poste)\b~', $value)
            || (bool) preg_match('~\b(?:notre|mon|mes|les)\s+(?:equipe|collaborateurs?|salaries?|employes?)\b~', $value);
    }
}
