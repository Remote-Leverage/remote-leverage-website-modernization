const sharp=require('sharp');
(async()=>{
  const [,,src,y0,x0,x1]=process.argv;
  const {data,info}=await sharp(src).ensureAlpha().raw().toBuffer({resolveWithObject:true});
  const y=+y0; const counts={};
  for(let x=+x0;x<=+x1;x++){
    const i=(y*info.width+x)*info.channels;
    const hex='#'+[data[i],data[i+1],data[i+2]].map(v=>v.toString(16).padStart(2,'0')).join('').toUpperCase();
    counts[hex]=(counts[hex]||0)+1;
  }
  Object.entries(counts).sort((a,b)=>b[1]-a[1]).slice(0,10).forEach(([h,c])=>console.log(h,c));
})();
