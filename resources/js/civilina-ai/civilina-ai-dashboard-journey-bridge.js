(function (root, factory) {
    'use strict';

    const api = factory();

    if (
        typeof module === 'object' &&
        module.exports
    ) {
        module.exports = api;
    } else {
        root.CivilinaAIDashboardJourneyBridge = api;
    }
})(
    typeof globalThis !== 'undefined'
        ? globalThis
        : this,

    function () {
        'use strict';

        const BRIDGE_VERSION = '1.0.0';

        function normalize(value) {
            if (
                value === null ||
                value === undefined
            ) {
                return '';
            }

            return String(value).trim();
        }

        function normalizeKey(value) {
            return normalize(value)
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase();
        }

        function nullableText(value) {
            const text = normalize(value);

            if (!text) {
                return null;
            }

            const key = normalizeKey(text);

            if (
                [
                    'inconnu',
                    'inconnue',
                    'inconnus',
                    'inconnues',
                    'unknown',
                    'n/a',
                    'na',
                    'a definir',
                    'non defini',
                    'non definie'
                ].includes(key)
            ) {
                return null;
            }

            return text;
        }

        function extractKpiId(value) {
            const match =
                normalize(value).match(
                    /\bKPI-\d+\b/i
                );

            return match
                ? match[0].toUpperCase()
                : null;
        }

        function parseField(segment) {
            const text = normalize(segment);
            const index = text.indexOf(':');

            if (index < 0) {
                return null;
            }

            return {
                label: normalizeKey(
                    text.slice(0, index)
                ),

                value: normalize(
                    text.slice(index + 1)
                )
            };
        }

        function parseKpiLine(line) {
            const segments =
                normalize(line)
                    .split('|')
                    .map(normalize)
                    .filter(Boolean);

            if (!segments.length) {
                return null;
            }

            const id =
                extractKpiId(
                    segments[0]
                );

            if (!id) {
                return null;
            }

            const row = {
                id,
                name: null,
                value: null,
                unit: null,
                target: null,
                trend: null,
                source: null,
                dataOwner: null
            };

            segments
                .slice(1)
                .forEach(function (segment) {
                    const field =
                        parseField(segment);

                    if (!field) {
                        return;
                    }

                    if (
                        [
                            'indicateur',
                            'kpi',
                            'nom',
                            'name'
                        ].includes(field.label)
                    ) {
                        row.name =
                            nullableText(
                                field.value
                            );

                        return;
                    }

                    if (
                        [
                            'valeur',
                            'value',
                            'actuel',
                            'actual'
                        ].includes(field.label)
                    ) {
                        row.value =
                            nullableText(
                                field.value
                            );

                        return;
                    }

                    if (
                        [
                            'unite',
                            'unit'
                        ].includes(field.label)
                    ) {
                        row.unit =
                            nullableText(
                                field.value
                            );

                        return;
                    }

                    if (
                        [
                            'cible',
                            'target',
                            'objectif'
                        ].includes(field.label)
                    ) {
                        row.target =
                            nullableText(
                                field.value
                            );

                        return;
                    }

                    if (
                        [
                            'tendance',
                            'trend'
                        ].includes(field.label)
                    ) {
                        row.trend =
                            nullableText(
                                field.value
                            );
                    }
                });

            /*
             * Never invent a KPI name from its identifier.
             */
            if (!row.name) {
                return null;
            }

            return row;
        }

        function parseDataSourceLine(line) {
            const segments =
                normalize(line)
                    .split('|')
                    .map(normalize)
                    .filter(Boolean);

            if (!segments.length) {
                return null;
            }

            const id =
                extractKpiId(
                    segments[0]
                );

            if (!id) {
                return null;
            }

            const row = {
                id,
                source: null,
                dataOwner: null
            };

            segments
                .slice(1)
                .forEach(function (segment) {
                    const field =
                        parseField(segment);

                    if (!field) {
                        return;
                    }

                    if (
                        [
                            'source',
                            'data source'
                        ].includes(field.label)
                    ) {
                        row.source =
                            nullableText(
                                field.value
                            );

                        return;
                    }

                    if (
                        [
                            'proprietaire',
                            'owner',
                            'data owner',
                            'responsable'
                        ].includes(field.label)
                    ) {
                        row.dataOwner =
                            nullableText(
                                field.value
                            );
                    }
                });

            return row;
        }

        function parseKpis(value) {
            if (
                Array.isArray(value) &&
                value.every(function (item) {
                    return (
                        item &&
                        typeof item === 'object'
                    );
                })
            ) {
                return value;
            }

            return normalize(value)
                .split(/\r?\n/)
                .map(parseKpiLine)
                .filter(Boolean);
        }

        function parseDataSources(value) {
            if (
                Array.isArray(value) &&
                value.every(function (item) {
                    return (
                        item &&
                        typeof item === 'object'
                    );
                })
            ) {
                return value;
            }

            return normalize(value)
                .split(/\r?\n/)
                .map(parseDataSourceLine)
                .filter(Boolean);
        }

        function prepare(input) {
            const safeInput =
                input &&
                typeof input === 'object'
                    ? input
                    : {};

            const sourceAnswers =
                safeInput.answers &&
                typeof safeInput.answers === 'object'
                    ? safeInput.answers
                    : {};

            const kpis =
                parseKpis(
                    sourceAnswers.dashboardKpi
                );

            const dataSources =
                parseDataSources(
                    sourceAnswers.dashboardDataSource
                );

            const sourceById = {};

            dataSources.forEach(
                function (source) {
                    if (
                        source &&
                        source.id
                    ) {
                        sourceById[source.id] =
                            source;
                    }
                }
            );

            const mergedKpis =
                kpis.map(function (kpi) {
                    const source =
                        sourceById[kpi.id] ||
                        null;

                    return Object.assign(
                        {},
                        kpi,
                        {
                            source:
                                source
                                    ? source.source
                                    : (
                                        kpi.source ||
                                        null
                                    ),

                            dataOwner:
                                source
                                    ? source.dataOwner
                                    : (
                                        kpi.dataOwner ||
                                        null
                                    )
                        }
                    );
                });

            const kpiIds =
                new Set(
                    mergedKpis.map(
                        function (kpi) {
                            return kpi.id;
                        }
                    )
                );

            const unassociatedDataSources =
                dataSources.filter(
                    function (source) {
                        return (
                            source &&
                            !kpiIds.has(
                                source.id
                            )
                        );
                    }
                );

            const builderInput =
                Object.assign(
                    {},
                    safeInput,
                    {
                        answers:
                            Object.assign(
                                {},
                                sourceAnswers,
                                {
                                    dashboardKpi:
                                        mergedKpis,

                                    dashboardDataSource:
                                        dataSources
                                }
                            ),

                        unassociatedDataSources:
                            unassociatedDataSources
                    }
                );

            return {
                bridgeVersion:
                    BRIDGE_VERSION,

                ready:
                    mergedKpis.length > 0 &&
                    dataSources.length > 0,

                status:
                    mergedKpis.length > 0 &&
                    dataSources.length > 0
                        ? 'READY_FOR_BUILDER'
                        : 'INCOMPLETE',

                builderInput,

                traceability: {
                    associationMethod:
                        'explicit-kpi-id',

                    unassociatedDataSources,

                    inventedFacts:
                        false
                }
            };
        }

        return {
            BRIDGE_VERSION,
            parseKpis,
            parseDataSources,
            prepare
        };
    }
);