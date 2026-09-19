import { decode } from './pnglib.mjs';
const img = decode(process.argv[2]);
const px=(x,y)=>{const i=(y*img.w+x)*img.ch;return `rgb(${img.data[i]},${img.data[i+1]},${img.data[i+2]})`;};
for (const pair of process.argv.slice(3)) { const [x,y]=pair.split(',').map(Number); console.log(`  (${x},${y}) = ${px(x,y)}`); }
