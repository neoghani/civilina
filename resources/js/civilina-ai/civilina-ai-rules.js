(function (root, factory) {
    'use strict';

    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.CivilinaAIRules = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    function readField(context, field) {
        return field.split('.').reduce(function (value, key) {
            return value === undefined || value === null ? undefined : value[key];
        }, context);
    }

    function evaluateCondition(context, condition) {
        const value = readField(context, condition.field);
        if (Object.prototype.hasOwnProperty.call(condition, 'equals')) return value === condition.equals;
        if (Object.prototype.hasOwnProperty.call(condition, 'notEquals')) return value !== condition.notEquals;
        if (condition.oneOf) return condition.oneOf.includes(value);
        if (Object.prototype.hasOwnProperty.call(condition, 'includes')) return Array.isArray(value) && value.includes(condition.includes);
        if (Object.prototype.hasOwnProperty.call(condition, 'containsText')) {
            return typeof value === 'string' && value.toLocaleLowerCase('fr').includes(String(condition.containsText).toLocaleLowerCase('fr'));
        }
        if (Object.prototype.hasOwnProperty.call(condition, 'exists')) {
            const exists = value !== undefined && value !== null && value !== '';
            return condition.exists ? exists : !exists;
        }
        if (Object.prototype.hasOwnProperty.call(condition, 'greaterThan')) return Number(value) > condition.greaterThan;
        if (Object.prototype.hasOwnProperty.call(condition, 'lessThan')) return Number(value) < condition.lessThan;
        return false;
    }

    function evaluateRule(context, rule) {
        const all = rule.all || [];
        const any = rule.any || [];
        const none = rule.none || [];
        const allPassed = all.every(function (condition) { return evaluateCondition(context, condition); });
        const anyPassed = any.length === 0 || any.some(function (condition) { return evaluateCondition(context, condition); });
        const nonePassed = none.every(function (condition) { return !evaluateCondition(context, condition); });
        return allPassed && anyPassed && nonePassed;
    }

    function matchingRules(context, rules) {
        return rules.filter(function (rule) { return evaluateRule(context, rule); });
    }

    return {
        readField: readField,
        evaluateCondition: evaluateCondition,
        evaluateRule: evaluateRule,
        matchingRules: matchingRules
    };
});
