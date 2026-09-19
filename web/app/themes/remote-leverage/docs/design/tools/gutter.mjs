import { decode } from './pnglib.mjs';
const img = decode(process.argv[2]);
const px=(x,y)=>{const i=(y*img.w+x)*img.ch;return [img.data[i],img.data[i+1],img.data[i+2]];};
const near=(a,b,t=10)=>Math.abs(a[0]-b[0])+Math.abs(a[1]-b[1])+Math.abs(a[2]-b[2])<=t;
// At a given row, find where the page background stops and a card begins on each side.
for (const y of process.argv.slice(3).map(Number)) {
  const bg = px(1,y);
  let l=-1,r=-1;
  for(let x=0;x<img.w;x++){ if(!near(px(x,y),bg)){ l=x; break; } }
  for(let x=img.w-1;x>=0;x--){ if(!near(px(x,y),bg)){ r=x; break; } }
  console.log(`  y=${String(y).padStart(5)}  bg=rgb(${bg})  card x ${l}..${r}  → gutters L=${l} R=${img.w-1-r}`);
}
