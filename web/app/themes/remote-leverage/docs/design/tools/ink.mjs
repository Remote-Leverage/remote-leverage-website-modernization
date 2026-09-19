import { decode } from './pnglib.mjs';
// Report the ink bounding box of text inside a region, by contrast against the region's
// dominant (background) colour. Width of a known string is the cheapest way to compare
// font-size + letter-spacing between two renders of the same copy.
const img = decode(process.argv[2]);
const regions = process.argv.slice(3); // y0:y1:x0:x1
const px=(x,y)=>{const i=(y*img.w+x)*img.ch;return [img.data[i],img.data[i+1],img.data[i+2]];};
for (const r of regions) {
  const [y0,y1,x0,x1] = r.split(':').map(Number);
  // background = most common colour in the region
  const hist=new Map();
  for(let y=y0;y<y1;y++) for(let x=x0;x<x1;x++){ const k=px(x,y).join(','); hist.set(k,(hist.get(k)||0)+1); }
  const bg=[...hist.entries()].sort((a,b)=>b[1]-a[1])[0][0].split(',').map(Number);
  let minx=1e9,maxx=-1,miny=1e9,maxy=-1,n=0;
  const rows=[];
  for(let y=y0;y<y1;y++){
    let rn=0;
    for(let x=x0;x<x1;x++){
      const p=px(x,y);
      const d=Math.abs(p[0]-bg[0])+Math.abs(p[1]-bg[1])+Math.abs(p[2]-bg[2]);
      if(d>90){ rn++; n++; if(x<minx)minx=x; if(x>maxx)maxx=x; if(y<miny)miny=y; if(y>maxy)maxy=y; }
    }
    rows.push(rn);
  }
  console.log(`region ${r}  bg=rgb(${bg})  ink x ${minx}..${maxx} (w=${maxx-minx+1})  y ${miny}..${maxy} (h=${maxy-miny+1})  px=${n}`);
}
