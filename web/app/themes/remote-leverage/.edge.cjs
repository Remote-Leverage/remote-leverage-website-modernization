const sharp=require('sharp');
(async()=>{
  const [,,src,mode,fixed,from,to,target]=process.argv;
  const {data,info}=await sharp(src).ensureAlpha().raw().toBuffer({resolveWithObject:true});
  const hexAt=(x,y)=>{const i=(y*info.width+x)*info.channels;return '#'+[data[i],data[i+1],data[i+2]].map(v=>v.toString(16).padStart(2,'0')).join('').toUpperCase();};
  const runs=[]; let prev=null,start=null;
  for(let p=+from;p<=+to;p++){
    const [x,y]= mode==='h'?[p,+fixed]:[+fixed,p];
    const h=hexAt(x,y);
    if(h!==prev){ if(prev!==null) runs.push([prev,start,p-1]); prev=h; start=p; }
  }
  runs.push([prev,start,+to]);
  runs.filter(r=>r[2]-r[1]>=3).forEach(r=>console.log(`${r[0]}  ${r[1]}..${r[2]}  (${r[2]-r[1]+1}px)`));
})();
