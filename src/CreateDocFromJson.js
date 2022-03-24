const path = require('path');
const fs = require('fs');
let app = {};

module.exports = function parserSchemaElements(elements, element, block, filename) {
    if (element.name !== 'apijson') {
        return elements;
    }
    elements.pop();
    const tagInfo = element.content.trim().match(/^\(((?<key>.*)=)?(?<path>.+)\)\s+(?<type>.*)$/);
    if (!tagInfo) {
        app.log.error(`Error parsing schema ${element.source} in ${filename}`)
        process.exit(1);
    }
    let {key, path: jsonPath, type} = tagInfo.groups;
    jsonPath = path.join(process.cwd(), path.dirname(filename), jsonPath);
    if (!fs.statSync(jsonPath, {throwIfNoEntry: false})) {
        app.log.error(`File: (${jsonPath}) not found`)
        process.exit(1);
    }
    const data = JSON.parse(fs.readFileSync(jsonPath, {encoding: 'utf8'}));
    data.forEach(param => {
        let allowedValues = getAllowedValues(param);
        let field = getField(param, key);
        const newElement = {
            source    : `@${type}`,
            name      : type.toLowerCase(),
            sourceName: type,
            content   : `{${param.type}${allowedValues}} ${field} ${param.description}`,
        }
        newElement.source += ' ' + newElement.content;
        elements.push(newElement);
    });
    return elements;
}

function getAllowedValues(param) {
    if (!param.allowedValues) {
        return '';
    }
    if (param.allowedValues.includes(',')) {
        return '=' + param.allowedValues;
    }
    return `{${param.allowedValues}}`;
}

function getField(param, parent = '') {
    let field = param.field;
    if (parent) {
        field = `${parent}.${field}`;
    }
    if (param.defaultValue) {
        field += '=' + param.defaultValue;
    }
    if (param.optional) {
        field = `[${field}]`
    }
    return field;
}
