const sharp=require('sharp');
(async()=>{
  const src=process.argv[2];
  const pts=process.argv.slice(3).map(s=>s.split(',').map(Number));
  const {data,info}=await sharp(src).ensureAlpha().raw().toBuffer({resolveWithObject:true});
  for(const [x,y] of pts){
    const i=(y*info.width+x)*info.channels;
    const hex='#'+[data[i],data[i+1],data[i+2]].map(v=>v.toString(16).padStart(2,'0')).join('').toUpperCase();
    console.log(`(${x},${y}) ${hex}`);
  }
})();
