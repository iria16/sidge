const fs = require('fs');
const path = require('path');
const sass = require('sass');

const rootDir = path.resolve(__dirname, '..');
const inputFile = path.join(rootDir, 'resources', 'scss', 'app.scss');
const outputFile = path.join(rootDir, 'public', 'assets', 'css', 'app.css');

console.log('Compilando:', inputFile, '->', outputFile);
const result = sass.compile(inputFile, {
  style: 'compressed',
  sourceMap: false
});

fs.writeFileSync(outputFile, result.css);
console.log('¡Compilado con éxito! Tamaño:', result.css.length, 'bytes');
