"use strict";(globalThis.webpackChunksemantic_linkboss=globalThis.webpackChunksemantic_linkboss||[]).push([[748],{3748:(e,t,a)=>{a.r(t),a.d(t,{default:()=>g});var r=a(1609),n=a(7723),s=a(1083),l=a(8465),o=a.n(l),i=a(9939),c=a(982),m=a(6188),d=a(7875);const g=({isWizard:e=!1})=>{const[t,a]=(0,r.useState)(!1),[l,g]=(0,r.useState)("");(0,r.useEffect)((()=>{g(LinkBossConfig?.current_user?.domain)}),[]);const[u,b]=(0,r.useState)(""),[h,f]=(0,r.useState)(!0);(0,r.useEffect)((()=>{(async()=>{try{const e=await s.A.get("/wp-json/linkboss/v1/settings",{params:{action:"api_key"},headers:{"X-WP-Nonce":LinkBossConfig.nonce}});b(e?.data?.api_key||""),p(e?.data?.api_key||""),f(!1)}catch(e){console.error("Error fetching settings:",e),f(!1)}})()}),[]);const[k,p]=(0,r.useState)("");return h?(0,r.createElement)(r.Fragment,null,(0,r.createElement)("div",{className:"text-center"},(0,n.__)("Loading","semantic-linkboss"),"..."),(0,r.createElement)("div",{className:"flex justify-center items-center h-40 mt-12"},(0,r.createElement)("div",{className:"animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-blue-500"}))):(0,r.createElement)("div",{className:"mt-12 pt-6"},(0,r.createElement)("div",{className:"mb-12 relative flex flex-col bg-clip-border rounded-xl bg-white dark:bg-gray-900 text-gray-700 shadow-sm"},(0,r.createElement)(i.A,{title:"System Settings",desc:"It is important to be aware of your system settings and make sure that they are correctly configured for optimal performance."}),(0,r.createElement)("div",{className:"p-6"},(0,r.createElement)("div",{className:"grid grid-cols-1 gap-10 sm:grid-cols-2"},(0,r.createElement)("div",null,(0,r.createElement)("form",{onSubmit:async t=>{if(t.preventDefault(),""!==k)try{o().fire({title:"Loading...",allowOutsideClick:!1,didOpen:()=>{o().showLoading()}});const t=await s.A.post("/wp-json/linkboss/v1/auth",null,{params:{api_key:u},headers:{"X-WP-Nonce":LinkBossConfig.nonce}});localStorage.setItem("linkboss_setup_wizard_step",1),o().fire({icon:"success",title:(0,n.__)("Success","semantic-linkboss"),html:t?.data?.message,showConfirmButton:!1,timer:2500,willClose:()=>{window.location.reload()}}),e&&setTimeout((()=>{o().fire({title:"Loading...",allowOutsideClick:!1,didOpen:()=>{window.location.reload(),o().showLoading()}})}),2e3)}catch(e){o().fire({icon:"error",title:e?.response?.data?.data?.title||"An error occurred",showConfirmButton:!0,html:e?.response?.data?.message||"Please try again"})}else o().fire({icon:"error",title:"API Key is required",showConfirmButton:!0})}},(0,r.createElement)("div",{className:"flex items-center gap-6"},(0,r.createElement)("div",{className:"w-[80%]"},(0,r.createElement)("h6",{className:"mb-2 text-slate-800 text-lg font-semibold dark:text-white"},(0,n.__)("Connect your WP site with LinkBoss App","semantic-linkboss")),(0,r.createElement)("p",{className:"mb-4 text-sm text-gray-500 dark:text-gray-400"},"You can get your API Key ",(0,r.createElement)("strong",null,"free")," from",(0,r.createElement)("a",{href:"https://app.linkboss.io",target:"_blank",className:"font-medium text-blue-600 underline dark:text-blue-500 ml-1 mr-1"},"https://app.linkboss.io")))),(0,r.createElement)("div",{className:"relative"},(0,r.createElement)("label",{className:"block mb-2 text-sm font-medium text-gray-900 dark:text-white"},"API Key"),(0,r.createElement)("div",{className:"relative"},(0,r.createElement)("div",{className:"absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none text-gray-500 dark:text-gray-400"},(0,r.createElement)("svg",{fill:"currentColor",className:"w-4 h-4","aria-hidden":"true",xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 512 512"},(0,r.createElement)("path",{d:"M336 352c97.2 0 176-78.8 176-176S433.2 0 336 0S160 78.8 160 176c0 18.7 2.9 36.8 8.3 53.7L7 391c-4.5 4.5-7 10.6-7 17v80c0 13.3 10.7 24 24 24h80c13.3 0 24-10.7 24-24V448h40c13.3 0 24-10.7 24-24V384h40c6.4 0 12.5-2.5 17-7l33.3-33.3c16.9 5.4 35 8.3 53.7 8.3zM376 96a40 40 0 1 1 0 80 40 40 0 1 1 0-80z"}))),(0,r.createElement)("input",{value:u||"",type:t?"text":"password",onChange:e=>{p(e.target.value),b(e.target.value)},name:"linkboss_api_key",className:"block w-full p-4 ps-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"}),(0,r.createElement)("div",{className:"text-white absolute end-2.5 bottom-2.5 bg-indigo-700 hover:bg-indigo-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-2.5 py-2 dark:bg-indigo-600 dark:hover:bg-indigo-700 dark:focus:ring-blue-800 cursor-pointer leading-none",onClick:()=>{a(!t)}},(0,r.createElement)(c.g,{icon:t?m.pS3:m.k6j,className:"h-4 w-4"})))),(0,r.createElement)("button",{type:"submit",className:"mt-6 text-white bg-indigo-700 hover:bg-indigo-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-md w-full sm:w-auto px-6 py-3.5 text-center dark:bg-indigo-600 dark:hover:bg-indigo-700 dark:focus:ring-blue-800"},(0,n.__)("Save Settings","semantic-linkboss")))),(0,r.createElement)("div",null,(0,r.createElement)("h3",{className:"text-lg font-semibold text-gray-900 dark:text-white mb-2"},(0,n.__)("Authentication Process:","semantic-linkboss")),(0,r.createElement)("ol",{className:"relative border-s border-gray-200 dark:border-gray-700"},(0,r.createElement)("li",{className:"mb-10 ms-4"},(0,r.createElement)("div",{className:"absolute w-3 h-3 bg-gray-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-gray-900 dark:bg-gray-700"}),(0,r.createElement)("time",{className:"mb-1 text-sm font-normal leading-none text-gray-400 dark:text-gray-500"},"Step 1"),(0,r.createElement)("h3",{className:"text-lg font-semibold text-gray-900 dark:text-white"},"Copy the API Key from LinkBoss App"),(0,r.createElement)("p",{className:"mb-4 text-base font-normal text-gray-500 dark:text-gray-400"},"Login/Register at ",(0,r.createElement)("a",{href:"https://app.linkboss.io",target:"_blank",className:"font-medium text-blue-600 underline dark:text-blue-500"},"https://app.linkboss.io"),(0,r.createElement)("br",null),"Add this domain url ",(0,r.createElement)("strong",null,"(",LinkBossConfig?.current_user?.domain,")"),(0,r.createElement)("span",{onClick:()=>{navigator.clipboard&&navigator.clipboard.writeText?navigator.clipboard.writeText(l).then((()=>{o().fire({icon:"success",title:"Domain copied successfully",showConfirmButton:!1,timer:1500})})).catch((e=>{console.error("Could not copy text: ",e)})):console.error("Clipboard API not supported")},className:"font-medium text-blue-600 underline dark:text-blue-500"},(0,r.createElement)(c.g,{icon:m.jPR,className:"h-4 w-4"}))," on the app. ",(0,r.createElement)("br",null),"Then Copy the API Key from there."),(0,r.createElement)("a",{href:"https://www.youtube.com/watch?v=rZX93rkjG2c",target:"_blank",className:"inline-flex items-center justify-center px-4 py-3 text-base font-medium text-white rounded-lg bg-red-700 hover:bg-red-800 focus:ring-4 focus:ring-red-300 dark:hover:text-white"},(0,r.createElement)(c.g,{icon:d.B4m,className:"h-6 w-6 me-2"}),"Watch Tutorial")),(0,r.createElement)("li",{className:"mb-10 ms-4"},(0,r.createElement)("div",{className:"absolute w-3 h-3 bg-gray-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-gray-900 dark:bg-gray-700"}),(0,r.createElement)("time",{className:"mb-1 text-sm font-normal leading-none text-gray-400 dark:text-gray-500"},"Step 2"),(0,r.createElement)("h3",{className:"text-lg font-semibold text-gray-900 dark:text-white"},"Paste API Key on LinkBoss Plugin Settings"),(0,r.createElement)("p",{className:"text-base font-normal text-gray-500 dark:text-gray-400"},'Paste the API key inside the "API Key" field and click "Save Settings".')),(0,r.createElement)("li",{className:"ms-4"},(0,r.createElement)("div",{className:"absolute w-3 h-3 bg-gray-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-gray-900 dark:bg-gray-700"}),(0,r.createElement)("time",{className:"mb-1 text-sm font-normal leading-none text-gray-400 dark:text-gray-500"},"Step 3"),(0,r.createElement)("h3",{className:"text-lg font-semibold text-gray-900 dark:text-white"},"Sync Site with LinkBoss APP"),(0,r.createElement)("p",{className:"text-base font-normal text-gray-500 dark:text-gray-400"},'Go to the "Sync" tab, click on "Prepare Data" and wait. Then click on "Sync Now" button.'))))))))}},9939:(e,t,a)=>{a.d(t,{A:()=>s});var r=a(1609),n=a(7723);const s=({title:e,desc:t})=>(0,r.createElement)("div",{className:"relative bg-clip-border mx-4 rounded-xl overflow-hidden bg-gradient-to-tr from-indigo-800 to-indigo-400 text-white shadow-indigo-500/40 shadow-lg -mt-12 mb-8 p-6"},(0,r.createElement)("div",{className:"flex w-full items-center justify-between"},(0,r.createElement)("div",null,(0,r.createElement)("h6",{className:"block antialiased tracking-normal font-sans text-base font-semibold leading-relaxed text-white mt-0 mb-1"},(0,n.__)(e,"semantic-linkboss")),(0,r.createElement)("div",{className:"block antialiased font-sans text-md font-normal dark:text-gray-300"},(0,n.__)(t,"semantic-linkboss")))))}}]);