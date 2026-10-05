'use client';

import {useState} from 'react';
import {Check,Copy} from 'lucide-react';

type Props = {
  id:string;
  name:string;
  value:string;
  template:string;
  rows:number;
  onChange:(value:string)=>void;
  onCopyError:(message:string)=>void;
};

export function JsonParameterField({id,name,value,template,rows,onChange,onCopyError}:Props){
  const [copied,setCopied]=useState(false);
  async function copyJson(){
    try {
      await navigator.clipboard.writeText(value.trim()?value:template);
      setCopied(true);
      setTimeout(()=>setCopied(false),1500);
    } catch {
      onCopyError('Your browser could not copy the JSON. Insert the template, then select and copy it manually.');
    }
  }
  return <>
    <div className="json-field-actions">
      <button type="button" disabled={Boolean(value.trim())} title={value.trim()?'Clear this field to insert its template':'Fill this field with its JSON template'} onClick={()=>{onChange(template);setCopied(false);}}>Insert template</button>
      <button type="button" aria-label={'Copy JSON for '+name} onClick={()=>void copyJson()}>{copied?<Check size={14}/>:<Copy size={14}/>}<span>{copied?'Copied':'Copy JSON'}</span></button>
    </div>
    <textarea id={id} rows={rows} placeholder={template} value={value} onChange={e=>{onChange(e.target.value);setCopied(false);}}/>
    <small className="json-template-help">Edit the template to match your configuration.</small>
  </>;
}
