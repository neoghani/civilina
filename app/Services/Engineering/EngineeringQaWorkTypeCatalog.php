<?php

namespace App\Services\Engineering;

class EngineeringQaWorkTypeCatalog
{
    public const REINFORCED_CONCRETE = 'reinforced_concrete';
    public const STRUCTURAL_STEEL = 'structural_steel';
    public const MIXED_STRUCTURE = 'mixed_structure';
    public const EARTHWORKS_ROAD = 'earthworks_road';

    public function workTypes(): array
    {
        return [
            self::REINFORCED_CONCRETE => 'Béton armé',
            self::STRUCTURAL_STEEL => 'Charpente métallique',
            self::MIXED_STRUCTURE => 'Structure mixte BA + charpente métallique',
            self::EARTHWORKS_ROAD => 'Terrassement / Route',
        ];
    }

    public function templates(string $workType): array
    {
        return match ($workType) {
            self::REINFORCED_CONCRETE => $this->reinforcedConcrete(),
            self::STRUCTURAL_STEEL => $this->structuralSteel(),
            self::MIXED_STRUCTURE => array_merge(
                $this->prefixTemplates($this->reinforcedConcrete(), 'MIX-RC-'),
                $this->prefixTemplates($this->structuralSteel(), 'MIX-SS-')
            ),
            self::EARTHWORKS_ROAD => $this->earthworksRoad(),
            default => [],
        };
    }

    public function handoverTemplates(string $workType): array
    {
        if ($workType !== self::MIXED_STRUCTURE) {
            return [];
        }

        return [[
            'key' => 'MIX-HO-RC-STEEL',
            'code_suffix' => 'HO-RC-CM',
            'from_work_type' => self::REINFORCED_CONCRETE,
            'to_work_type' => self::STRUCTURAL_STEEL,
            'title' => 'Handover béton armé → charpente métallique',
            'criteria' => [
                'Réception des supports en béton armé enregistrée',
                'Résultats béton requis disponibles et acceptés selon le référentiel projet',
                'Axes et implantation des ancrages contrôlés',
                'Altimétrie / niveaux des appuis contrôlés',
                'Écartement et position des tiges d’ancrage contrôlés',
                'Réserves bloquantes levées',
                'Autorisation client de démarrer le montage enregistrée',
            ],
        ]];
    }

    public function registerSchemas(): array
    {
        return [
            'concrete_pre_pour' => [
                'label' => 'Suivi pré-bétonnage',
                'fields' => [
                    'element_ref' => 'Élément / repère', 'zone' => 'Zone', 'inspection_date' => 'Date inspection',
                    'rebar_pv_ref' => 'PV ferraillage', 'formwork_pv_ref' => 'PV coffrage',
                    'client_authorization_ref' => 'Réf. autorisation bétonnage client',
                    'client_authorization_date' => 'Date/heure autorisation', 'pour_status' => 'Statut libération bétonnage',
                ],
            ],
            'concrete_fresh' => [
                'label' => 'Suivi béton frais',
                'fields' => [
                    'element_ref' => 'Élément / repère', 'zone' => 'Zone', 'concrete_class' => 'Classe / désignation béton',
                    'pour_date' => 'Date bétonnage', 'batch_or_truck_ref' => 'Gâchée / camion', 'volume_m3' => 'Volume (m³)',
                    'consistency_result' => 'Affaissement / consistance mesuré', 'temperature_c' => 'Température (°C)',
                    'sample_refs' => 'Références éprouvettes', 'requirement_source' => 'Source des critères', 'result' => 'Résultat',
                ],
            ],
            'concrete_strength' => [
                'label' => 'Suivi laboratoire béton 7 / 28 jours',
                'fields' => [
                    'element_ref' => 'Élément / repère', 'sample_ref' => 'Réf. éprouvette / série', 'age_days' => 'Âge (jours)',
                    'test_date' => 'Date essai', 'strength_mpa' => 'Résistance mesurée (MPa)',
                    'required_strength_mpa' => 'Exigence projet (MPa)', 'lab_report_ref' => 'Réf. rapport laboratoire',
                    'requirement_source' => 'Source exigence', 'result' => 'Résultat',
                ],
            ],
            'steel_receipt' => [
                'label' => 'Réception quantitative & qualitative charpente',
                'fields' => [
                    'element_type' => 'Type (poteau/poutre/solive/lisse/lierne/contreventement...)',
                    'mark' => 'Repère', 'description' => 'Description / profil', 'qty_planned' => 'Qté prévue',
                    'qty_received' => 'Qté reçue', 'steel_grade' => 'Qualité acier', 'certificate_ref' => 'Certificat matière',
                    'heat_batch_ref' => 'Coulée / lot', 'visual_condition' => 'État visuel', 'result' => 'Résultat',
                ],
            ],
            'steel_erection' => [
                'label' => 'Suivi conformité montage charpente',
                'fields' => [
                    'element_type' => 'Type élément', 'mark' => 'Repère', 'axis' => 'Axe / localisation',
                    'level' => 'Niveau / altimétrie', 'verticality' => 'Verticalité mesurée', 'alignment' => 'Alignement mesuré',
                    'requirement_source' => 'Plan / tolérance applicable', 'result' => 'Résultat',
                ],
            ],
            'bolt_torque' => [
                'label' => 'Suivi serrage à la clé dynamométrique',
                'fields' => [
                    'connection_ref' => 'Réf. assemblage', 'bolt_grade' => 'Classe boulon', 'bolt_diameter' => 'Diamètre',
                    'required_torque_nm' => 'Couple requis (N·m)', 'measured_torque_nm' => 'Couple contrôlé (N·m)',
                    'wrench_id' => 'Identifiant clé', 'calibration_ref' => 'Réf. étalonnage', 'check_date' => 'Date contrôle',
                    'requirement_source' => 'Source du couple requis', 'result' => 'Résultat',
                ],
            ],
            'earthwork_layer' => [
                'label' => 'Suivi couches de terrassement / remblai / CDF',
                'fields' => [
                    'zone_pk' => 'Zone / PK', 'material_type' => 'Nature matériau', 'layer_type' => 'Type couche',
                    'layer_no' => 'N° couche', 'planned_thickness_mm' => 'Épaisseur prévue (mm)',
                    'actual_thickness_mm' => 'Épaisseur réalisée (mm)', 'inspection_date' => 'Date',
                    'compaction_target' => 'Compacité exigée', 'compaction_measured' => 'Compacité mesurée',
                    'requirement_source' => 'Source exigence', 'result' => 'Résultat',
                ],
            ],
            'troxler_density' => [
                'label' => 'Suivi Troxler / densité in situ',
                'fields' => [
                    'zone_pk' => 'Zone / PK', 'layer_type' => 'Couche', 'test_ref' => 'Réf. essai', 'test_date' => 'Date',
                    'dry_density' => 'Densité sèche mesurée', 'max_dry_density' => 'Densité sèche max de référence',
                    'moisture_content' => 'Teneur en eau', 'compaction_percent' => 'Compacité calculée (%)',
                    'compaction_requirement' => 'Compacité exigée', 'requirement_source' => 'Source exigence', 'result' => 'Résultat',
                ],
            ],
            'plate_load' => [
                'label' => 'Suivi essai de plaque / portance',
                'fields' => [
                    'zone_pk' => 'Zone / PK', 'layer_type' => 'Couche', 'test_ref' => 'Réf. essai', 'test_date' => 'Date',
                    'criterion_name' => 'Indicateur / critère', 'requirement_value' => 'Exigence projet',
                    'measured_value' => 'Valeur mesurée', 'unit' => 'Unité', 'requirement_source' => 'Source exigence', 'result' => 'Résultat',
                ],
            ],
            'deflection' => [
                'label' => 'Suivi déflexion chaussée',
                'fields' => [
                    'pk_from' => 'PK début', 'pk_to' => 'PK fin', 'lane' => 'Voie', 'test_date' => 'Date',
                    'requirement_value' => 'Exigence projet', 'measured_value' => 'Déflexion mesurée', 'unit' => 'Unité',
                    'requirement_source' => 'Source exigence', 'result' => 'Résultat',
                ],
            ],
            'soil_lab' => [
                'label' => 'Suivi laboratoire sols / remblais',
                'fields' => [
                    'sample_ref' => 'Réf. échantillon', 'material_type' => 'Nature matériau', 'source' => 'Origine / carrière',
                    'sample_date' => 'Date prélèvement', 'test_type' => 'Type essai labo', 'test_method' => 'Méthode / référence',
                    'result_value' => 'Résultat', 'result_unit' => 'Unité', 'requirement_source' => 'Source exigence', 'result' => 'Conclusion',
                ],
            ],
        ];
    }

    public function registerSchema(?string $type): ?array
    {
        if (! $type) {
            return null;
        }

        return $this->registerSchemas()[$type] ?? null;
    }

    private function reinforcedConcrete(): array
    {
        return [
            $this->t('RC-REBAR', 'PV inspection ferraillage', 'inspection', 'concrete_pre_pour', 'HP', false),
            $this->t('RC-FORMWORK', 'PV inspection coffrage', 'inspection', 'concrete_pre_pour', 'HP', false),
            $this->t('RC-PREPOUR', 'PV inspection avant bétonnage + autorisation client', 'release', 'concrete_pre_pour', 'HP', true),
            $this->t('RC-FRESH', 'PV essais béton frais et prélèvements', 'test', 'concrete_fresh', 'WP', false),
            $this->t('RC-LAB-7D', 'PV / rapport laboratoire béton à 7 jours', 'laboratory', 'concrete_strength', 'WP', false),
            $this->t('RC-LAB-28D', 'PV / rapport laboratoire béton à 28 jours', 'laboratory', 'concrete_strength', 'HP', false),
            $this->t('RC-POSTPOUR', 'PV réception après bétonnage / ouvrage', 'inspection', null, 'WP', false),
        ];
    }

    private function structuralSteel(): array
    {
        return [
            $this->t('SS-RECEIPT', 'PV réception éléments de charpente métallique', 'inspection', 'steel_receipt', 'HP', false),
            $this->t('SS-ERECTION', 'PV conformité montage charpente métallique', 'inspection', 'steel_erection', 'WP', false),
            $this->t('SS-GEOMETRY', 'PV contrôle géométrie / aplomb / alignement / niveaux', 'inspection', 'steel_erection', 'WP', false),
            $this->t('SS-TORQUE', 'PV réception serrage à la clé dynamométrique', 'test', 'bolt_torque', 'HP', false),
        ];
    }

    private function earthworksRoad(): array
    {
        return [
            $this->t('ER-NATURAL-GROUND', 'PV réception sol naturel / fond de terrassement', 'inspection', 'earthwork_layer', 'HP', false),
            $this->t('ER-FILL-LAYER', 'PV réception couche de remblai / remblai sélectionné / CDF', 'inspection', 'earthwork_layer', 'WP', false),
            $this->t('ER-TROXLER', 'PV essai Troxler / densité in situ / compacité', 'test', 'troxler_density', 'WP', false),
            $this->t('ER-PLATE', 'PV essai de plaque / portance', 'test', 'plate_load', 'HP', false),
            $this->t('ER-LAB', 'PV essais laboratoire sols / remblais', 'laboratory', 'soil_lab', 'WP', false),
            $this->t('ER-GEOMETRY', 'PV contrôle épaisseur / niveau / profil / pente', 'inspection', 'earthwork_layer', 'WP', false),
            $this->t('ROAD-DEFLECTION', 'PV mesure de déflexion', 'test', 'deflection', 'WP', false),
        ];
    }

    private function t(string $key, string $title, string $recordType, ?string $registerType, string $checkpoint, bool $clientAuthorization): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'record_type' => $recordType,
            'register_type' => $registerType,
            'checkpoint_type' => $checkpoint,
            'requires_client_authorization' => $clientAuthorization,
        ];
    }

    private function prefixTemplates(array $templates, string $prefix): array
    {
        return array_map(function (array $template) use ($prefix): array {
            $template['key'] = $prefix.$template['key'];
            return $template;
        }, $templates);
    }
}
