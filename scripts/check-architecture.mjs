import { readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import ts from 'typescript';

const root = fileURLToPath(new URL('../resources/js/', import.meta.url));
const layers = new Set(['app', 'components', 'pages', 'hooks', 'utils', 'types', 'styles']);
const problems = [];
const files = [];
const names = new Map();
const pascalCase = /^[A-Z][A-Za-z0-9]*$/;
const componentFile = /^(?:[A-Z][A-Za-z0-9]*|use[A-Z][A-Za-z0-9]*)(?:\.[A-Za-z0-9]+)*\.(?:tsx?|css)$/;

function walk(directory) {
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const absolute = path.join(directory, entry.name);
    const relative = path.relative(root, absolute).split(path.sep).join('/');
    const key = relative.toLowerCase();
    if (names.has(key)) problems.push(`${relative}: colide com ${names.get(key)} em sistemas sem distinção de maiúsculas.`);
    names.set(key, relative);
    if (entry.isDirectory()) walk(absolute);
    else if (entry.isFile()) files.push(relative);
  }
}
walk(root);
const fileSet = new Set(files);

for (const entry of readdirSync(root, { withFileTypes: true })) {
  if (entry.isDirectory() && !layers.has(entry.name)) problems.push(`${entry.name}: camada desconhecida em src.`);
  if (entry.isFile() && entry.name !== 'main.tsx') problems.push(`${entry.name}: arquivo fora de uma camada de src.`);
}

for (const layer of ['components', 'pages']) {
  for (const entry of readdirSync(path.join(root, layer), { withFileTypes: true })) {
    if (!entry.isDirectory() || !pascalCase.test(entry.name)) {
      problems.push(`${layer}/${entry.name}: use uma pasta PascalCase.`);
    } else if (!fileSet.has(`${layer}/${entry.name}/${entry.name}.tsx`)) {
      problems.push(`${layer}/${entry.name}: falta o componente de entrada ${entry.name}.tsx.`);
    }
  }
}

const forbiddenLayers = {
  components: new Set(['app', 'pages']),
  pages: new Set(['app']),
  hooks: new Set(['app', 'pages', 'components']),
  utils: new Set(['app', 'pages', 'components', 'hooks']),
  types: new Set(['app', 'pages', 'components', 'hooks', 'utils']),
};

function checkImport(source, specifier) {
  if (!specifier.startsWith('.') && !specifier.startsWith('@/')) return;
  const layer = source.split('/')[0];
  const base = specifier.startsWith('@/') ? specifier.slice(2)
    : path.posix.normalize(path.posix.join(path.posix.dirname(source), specifier));
  const candidates = [base, `${base}.ts`, `${base}.tsx`, `${base}/index.ts`, `${base}/index.tsx`];
  const target = candidates.find(candidate => fileSet.has(candidate));
  if (!target) {
    const caseMismatch = candidates.map(candidate => names.get(candidate.toLowerCase())).find(Boolean);
    problems.push(`${source}: import ${specifier} não existe${caseMismatch ? `; verifique a capitalização de ${caseMismatch}` : ''}.`);
    return;
  }
  const targetLayer = target.split('/')[0];
  if (forbiddenLayers[layer]?.has(targetLayer)) {
    problems.push(`${source}: a camada ${layer} não pode depender de ${targetLayer} (${specifier}).`);
  }
  if (path.posix.dirname(target) !== path.posix.dirname(source) && !specifier.startsWith('@/')) {
    problems.push(`${source}: use @/ para importar arquivos de outra pasta (${specifier}).`);
  }
}

for (const relative of files) {
  const parts = relative.split('/');
  const layer = parts[0];
  const filename = parts.at(-1);
  if (['components', 'pages'].includes(layer)) {
    if (parts.length !== 3) problems.push(`${relative}: mantenha os arquivos junto ao componente ou tela responsável.`);
    if (/\.(?:tsx?|css)$/.test(filename) && !componentFile.test(filename)) {
      problems.push(`${relative}: use PascalCase para componentes, estilos e módulos do componente; useX para hooks locais.`);
    }
  }
  if (layer === 'hooks' && !/^use[A-Z][A-Za-z0-9]*(?:\.types)?\.ts$/.test(filename)) {
    problems.push(`${relative}: hooks compartilhados devem seguir useNome.ts ou useNome.types.ts.`);
  }
  if (layer === 'utils' && (!/^[a-z][A-Za-z0-9]*\.ts$/.test(filename) || filename.startsWith('use'))) {
    problems.push(`${relative}: utilitários devem ser camelCase.ts; hooks pertencem a hooks/.`);
  }
  if (layer === 'types' && !/^[A-Z][A-Za-z0-9]*\.types\.ts$/.test(filename)) {
    problems.push(`${relative}: tipos de domínio compartilhados devem seguir Nome.types.ts.`);
  }
  if (!/\.tsx?$/.test(filename)) continue;
  if (/\.(?:data|types)\.tsx$/.test(filename)) problems.push(`${relative}: dados e tipos devem usar .ts, sem JSX.`);
  const source = ts.createSourceFile(relative, readFileSync(path.join(root, relative), 'utf8'), ts.ScriptTarget.Latest, true);
  function visit(node) {
    if ((ts.isImportDeclaration(node) || ts.isExportDeclaration(node)) && node.moduleSpecifier && ts.isStringLiteral(node.moduleSpecifier)) {
      checkImport(relative, node.moduleSpecifier.text);
    } else if (ts.isCallExpression(node) && node.expression.kind === ts.SyntaxKind.ImportKeyword && node.arguments[0] && ts.isStringLiteral(node.arguments[0])) {
      checkImport(relative, node.arguments[0].text);
    }
    ts.forEachChild(node, visit);
  }
  visit(source);
}

if (problems.length) {
  console.error(`Arquitetura: ${problems.length} problema(s).\n${problems.map(problem => `- ${problem}`).join('\n')}`);
  process.exitCode = 1;
} else {
  console.log(`Arquitetura validada: ${files.length} arquivos, nomes, imports e limites entre camadas.`);
}
