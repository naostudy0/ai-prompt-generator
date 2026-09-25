const separator = '\n\n';

export const replaceModelFamilyDefault = (
    output,
    originalDefault,
    replacementDefault,
    selected,
) => {
    if (!selected) {
        return output;
    }
    if (originalDefault === '') {
        return replacementDefault === ''
            ? output
            : `${replacementDefault}${output === '' ? '' : separator}${output}`;
    }
    const start = output.indexOf(originalDefault);
    if (start < 0 || output.indexOf(originalDefault, start + 1) >= 0) {
        throw new Error('元の系統のデフォルト文面を特定できません。');
    }
    const before = output.slice(0, start);
    const after = output.slice(start + originalDefault.length);
    if (
        (before !== '' && !before.endsWith(separator)) ||
        (after !== '' && !after.startsWith(separator))
    ) {
        throw new Error('元の系統のデフォルト文面を特定できません。');
    }
    const prefix =
        replacementDefault === '' && before.endsWith(separator)
            ? before.slice(0, -separator.length)
            : before;
    const suffix =
        replacementDefault === '' && after.startsWith(separator)
            ? after.slice(separator.length)
            : after;
    return `${prefix}${replacementDefault}${suffix}`;
};
