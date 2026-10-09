<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include "header.php";

// ambil nama user untuk bubble "me"
$meName = '';
if (!empty($_SESSION['user']['name'])) $meName = (string)$_SESSION['user']['name'];
elseif (!empty($_SESSION['nama'])) $meName = (string)$_SESSION['nama'];
elseif (!empty($_SESSION['username'])) $meName = (string)$_SESSION['username'];
if ($meName === '') $meName = 'You';
?>
<style>
/* =========================
   EFIDI CHAT UI (WhatsApp-like)
   ========================= */
.ef-chat-wrap{
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid #eef2f7;
  background: #fff;
  min-height: 620px;
  height: calc(100vh - 180px);
}

.ef-chat{
  display:flex;
  height:100%;
}

/* LEFT LIST */
.ef-chat-list{
  width: 330px;
  border-right: 1px solid #eef2f7;
  background: #fbfcfe;
  display:flex;
  flex-direction:column;
}

.ef-chat-list-head{
  padding: 14px 14px 10px;
  border-bottom: 1px solid #eef2f7;
}

.ef-chat-search{
  position: relative;
}
.ef-chat-search i{
  position:absolute;
  left: 12px;
  top:50%;
  transform: translateY(-50%);
  color:#6b7280;
  font-size:18px;
}
.ef-chat-search input{
  width:100%;
  padding: 10px 12px 10px 40px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  font-size: 13px;
  outline:none;
  background:#fff;
}
.ef-chat-tabs{
  display:flex;
  gap:8px;
  margin-top: 10px;
}
.ef-chat-tab{
  font-size: 12px;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background:#fff;
  color:#374151;
  cursor:pointer;
  user-select:none;
}
.ef-chat-tab.is-active{
  background:#e6f7f9;
  border-color:#bfecef;
  color:#0f766e;
  font-weight:600;
}

.ef-chat-list-body{
  overflow:auto;
  padding: 8px;
}
.ef-chat-section-title{
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .14em;
  color:#94a3b8;
  text-transform:uppercase;
  padding: 10px 8px 6px;
}

/* chat item */
.ef-chat-item{
  display:flex;
  align-items:center;
  gap: 10px;
  padding: 10px 10px;
  border-radius: 12px;
  cursor:pointer;
  transition: background .12s ease;
  position: relative;
}
.ef-chat-item:hover{ background:#f3f4f6; }

.ef-chat-item.is-active{
  background: #20a1ad;
  color:#fff;
}
.ef-chat-item.is-active .muted,
.ef-chat-item.is-active .ef-chat-last,
.ef-chat-item.is-active .ef-chat-name{ color:#fff; opacity:.95; }

.ef-chat-avatar{
  width: 42px; height: 42px;
  border-radius: 999px;
  background: #e5e7eb;
  display:flex;
  align-items:center;
  justify-content:center;
  font-weight: 800;
  color:#374151;
  flex-shrink:0;
  position:relative;
}
.ef-dot{
  position:absolute;
  bottom: 2px;
  right: 2px;
  width: 10px;
  height: 10px;
  border-radius: 999px;
  background:#22c55e;
  border: 2px solid #fbfcfe;
}
.ef-chat-item.is-active .ef-dot{ border-color:#20a1ad; }

.ef-chat-meta{
  flex:1;
  min-width:0;
}
.ef-chat-row1{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:8px;
}
.ef-chat-name{
  font-size: 13px;
  font-weight: 700;
  color:#111827;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.ef-chat-time{
  font-size: 11px;
  color:#6b7280;
  white-space:nowrap;
}
.ef-chat-last{
  font-size: 12px;
  color:#6b7280;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
  margin-top: 2px;
}
.ef-chat-right{
  display:flex;
  flex-direction:column;
  align-items:flex-end;
  gap:6px;
  margin-left: 6px;
}
.ef-unread{
  min-width: 18px;
  height: 18px;
  padding: 0 6px;
  border-radius: 999px;
  background:#ef4444;
  color:#fff;
  font-size: 11px;
  font-weight: 800;
  display:inline-flex;
  align-items:center;
  justify-content:center;
}
.ef-pin{
  font-size: 15px;
  opacity: .75;
}
.ef-chat-item.is-active .ef-pin{ color:#fff; opacity:.95; }
.ef-chat-actions{
  display:none;
  position:absolute;
  right: 10px;
  top: 8px;
  gap: 8px;
}
.ef-chat-item:hover .ef-chat-actions{ display:flex; }
.ef-chat-action-btn{
  width: 26px;
  height: 26px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background:#fff;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#374151;
}
.ef-chat-item.is-active .ef-chat-action-btn{
  border-color: rgba(255,255,255,.35);
  background: rgba(255,255,255,.15);
  color:#fff;
}

/* RIGHT PANEL */
.ef-chat-main{
  flex:1;
  display:flex;
  flex-direction:column;
  background:#fff;
}

.ef-chat-main-head{
  padding: 12px 14px;
  border-bottom: 1px solid #eef2f7;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
}

.ef-chat-peer{
  display:flex;
  align-items:center;
  gap: 10px;
}
.ef-chat-peer .peer-avatar{
  width: 42px; height: 42px; border-radius:999px;
  background:#e5e7eb;
  display:flex; align-items:center; justify-content:center;
  font-weight:800; color:#374151;
}
.peer-name{
  font-weight: 800;
  color:#111827;
  line-height: 1.1;
}
.peer-sub{
  font-size: 12px;
  color:#6b7280;
  margin-top: 2px;
}

.ef-chat-tools{
  display:flex;
  gap:8px;
}
.ef-tool{
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background:#fff;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#0f172a;
}
.ef-tool:hover{ background:#f3f4f6; }

.ef-chat-messages{
  flex:1;
  overflow:auto;
  padding: 18px 18px 10px;
  background: #ffffff;
}
.ef-day{
  text-align:center;
  font-size: 11px;
  color:#94a3b8;
  margin: 10px 0 14px;
}

.ef-bubble-row{
  display:flex;
  margin: 8px 0;
}
.ef-bubble{
  max-width: 72%;
  padding: 10px 12px;
  border-radius: 12px;
  font-size: 13px;
  line-height: 1.35;
  position:relative;
  box-shadow: 0 1px 0 rgba(15,23,42,.06);
}
.ef-bubble .ef-time{
  display:block;
  font-size: 11px;
  color: rgba(255,255,255,.88);
  margin-top: 6px;
  text-align:right;
}
.ef-bubble.in .ef-time{ color:#6b7280; }

.ef-bubble.out{
  background: #5b6f9f;
  color:#fff;
  border-top-right-radius: 6px;
}
.ef-bubble.in{
  background: #f3f4f6;
  color:#111827;
  border-top-left-radius: 6px;
}

.ef-bubble-row.out{ justify-content:flex-end; }
.ef-bubble-row.in{ justify-content:flex-start; }

.ef-chat-compose{
  border-top: 1px solid #eef2f7;
  padding: 10px 12px;
  display:flex;
  gap: 10px;
  align-items:center;
}
.ef-compose-btn{
  width: 38px; height: 38px;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  background:#fff;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#374151;
}
.ef-compose-btn:hover{ background:#f3f4f6; }
.ef-compose-input{
  flex:1;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 10px 12px;
  outline:none;
  font-size: 13px;
}
.ef-send{
  background:#20a1ad;
  border-color:#20a1ad;
  color:#fff;
}
.ef-send:hover{ filter: brightness(.97); }

/* Responsive */
@media (max-width: 992px){
  .ef-chat-wrap{ height: auto; min-height: 650px; }
  .ef-chat{ flex-direction:column; }
  .ef-chat-list{ width:100%; border-right:0; border-bottom:1px solid #eef2f7; }
}
</style>

<div class="main-panel">
  <div class="content-wrapper">
    <div class="row">
      <div class="col-12 grid-margin stretch-card">
        <div class="card">
          <div class="card-body p-0">
            <div class="ef-chat-wrap">
              <div class="ef-chat">

                <!-- LEFT -->
                <aside class="ef-chat-list">
                  <div class="ef-chat-list-head">
                    <div class="ef-chat-search">
                      <i class="mdi mdi-magnify"></i>
                      <input id="chatSearch" type="text" placeholder="Search...">
                    </div>

                    <div class="ef-chat-tabs">
                      <div class="ef-chat-tab is-active" data-tab="all">All</div>
                      <div class="ef-chat-tab" data-tab="unread">Unread</div>
                      <div class="ef-chat-tab" data-tab="pinned">Pinned</div>
                    </div>
                  </div>

                  <div class="ef-chat-list-body" id="chatList"></div>
                </aside>

                <!-- RIGHT -->
                <section class="ef-chat-main">
                  <div class="ef-chat-main-head" id="chatHeader">
                    <div class="ef-chat-peer">
                      <div class="peer-avatar" id="peerAvatar">A</div>
                      <div>
                        <div class="peer-name" id="peerName">Select a chat</div>
                        <div class="peer-sub" id="peerSub">—</div>
                      </div>
                    </div>

                    <div class="ef-chat-tools">
                      <button class="ef-tool" type="button" title="Camera"><i class="mdi mdi-camera-outline"></i></button>
                      <button class="ef-tool" type="button" title="Video"><i class="mdi mdi-video-outline"></i></button>
                      <button class="ef-tool" type="button" title="Settings"><i class="mdi mdi-cog-outline"></i></button>
                      <button class="ef-tool" type="button" title="Help"><i class="mdi mdi-help-circle-outline"></i></button>
                    </div>
                  </div>

                  <div class="ef-chat-messages" id="chatMessages">
                    <div class="ef-day">Today</div>
                    <div class="text-muted" style="font-size:13px;">Pilih chat di sebelah kiri.</div>
                  </div>

                  <div class="ef-chat-compose">
                    <button class="ef-compose-btn" type="button" title="Emoji"><i class="mdi mdi-emoticon-outline"></i></button>
                    <button class="ef-compose-btn" type="button" title="Attach"><i class="mdi mdi-paperclip"></i></button>

                    <input class="ef-compose-input" id="chatInput" type="text" placeholder="Enter text here..." disabled>

                    <button class="ef-compose-btn ef-send" id="chatSend" type="button" title="Send" disabled>
                      <i class="mdi mdi-send"></i>
                    </button>
                  </div>
                </section>

              </div>
            </div><!-- /wrap -->
          </div>
        </div>
      </div>
    </div>
</div>

<script>
(function(){
  // =========================
  // MOCK DATA (sementara)
  // nanti tinggal ganti ke DB/API
  // =========================
  const ME_NAME = <?= json_encode($meName) ?>;

  const seedChats = [
    {
      id: "c1",
      name: "Aiden Chavez",
      online: true,
      lastSeen: "Last seen: 2 hours ago",
      lastMessage: "Project has been already finished and I have results to show you.",
      time: "10:15 AM",
      pinned: true,
      unread: 0,
      messages: [
        {from:"me", text:"Hi Aiden, how are you? How is the project coming along?", time:"10:10 AM"},
        {from:"them", text:"Are we meeting today?", time:"10:12 AM"},
        {from:"them", text:"Project has been already finished and I have results to show you.", time:"10:15 AM"},
      ]
    },
    {
      id: "c2",
      name: "Vincent Porter",
      online: false,
      lastSeen: "Last seen: 7 mins ago",
      lastMessage: "Ok, I’ll update the document.",
      time: "09:55 AM",
      pinned: false,
      unread: 2,
      messages: [
        {from:"them", text:"Bro bisa cek data BAI hari ini?", time:"09:48 AM"},
        {from:"them", text:"Ada yang belum masuk.", time:"09:49 AM"},
        {from:"me", text:"Siap, saya cek.", time:"09:51 AM"},
        {from:"them", text:"Ok, I’ll update the document.", time:"09:55 AM"},
      ]
    },
    {
      id: "c3",
      name: "Mike Thomas",
      online: true,
      lastSeen: "Online",
      lastMessage: "Noted. Nanti saya follow up ke tech.",
      time: "Yesterday",
      pinned: false,
      unread: 0,
      messages: [
        {from:"me", text:"Mike, untuk order kemarin aman?", time:"Yesterday"},
        {from:"them", text:"Noted. Nanti saya follow up ke tech.", time:"Yesterday"},
      ]
    },
    {
      id: "c4",
      name: "Christian Kelly",
      online: false,
      lastSeen: "Last seen: 10 hours ago",
      lastMessage: "Siap, saya kirim listnya.",
      time: "Mon",
      pinned: false,
      unread: 1,
      messages: [
        {from:"them", text:"Ada request export PDF yang baru.", time:"Mon"},
        {from:"them", text:"Siap, saya kirim listnya.", time:"Mon"},
      ]
    }
  ];

  // =========================
  // STORAGE (pin + unread)
  // =========================
  const LS_KEY = "ef_chat_state_v1";
  function loadState(){
    try{
      return JSON.parse(localStorage.getItem(LS_KEY) || "{}") || {};
    }catch(e){ return {}; }
  }
  function saveState(st){
    localStorage.setItem(LS_KEY, JSON.stringify(st || {}));
  }

  let state = loadState();
  let chats = seedChats.map(c => {
    const s = state[c.id] || {};
    return {
      ...c,
      pinned: (typeof s.pinned === "boolean") ? s.pinned : c.pinned,
      unread: (typeof s.unread === "number") ? s.unread : c.unread,
      messages: (Array.isArray(s.messages) ? s.messages : c.messages),
    };
  });

  function persistChat(chat){
    state[chat.id] = {
      pinned: chat.pinned,
      unread: chat.unread,
      messages: chat.messages
    };
    saveState(state);
  }

  function updateGlobalUnread(){
    const total = chats.reduce((a,c)=>a+(c.unread||0),0);
    localStorage.setItem("ef_chat_unread", String(total));
    if (window.EfidiChatBadgeUpdate) window.EfidiChatBadgeUpdate();
  }

  // =========================
  // UI RENDER
  // =========================
  const elList = document.getElementById("chatList");
  const elSearch = document.getElementById("chatSearch");
  const elTabs = document.querySelectorAll(".ef-chat-tab");

  const elPeerAvatar = document.getElementById("peerAvatar");
  const elPeerName = document.getElementById("peerName");
  const elPeerSub = document.getElementById("peerSub");
  const elMsgs = document.getElementById("chatMessages");

  const elInput = document.getElementById("chatInput");
  const elSend = document.getElementById("chatSend");

  let activeId = null;
  let activeTab = "all";

  function initials(name){
    const parts = String(name||"").trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return "?";
    return (parts[0][0] + (parts[1] ? parts[1][0] : "")).toUpperCase();
  }

  function sortChats(list){
    // pinned dulu, lalu by "unread", lalu by name (simple)
    return list.slice().sort((a,b)=>{
      if (!!b.pinned !== !!a.pinned) return b.pinned ? 1 : -1;
      if ((b.unread||0) !== (a.unread||0)) return (b.unread||0) - (a.unread||0);
      return a.name.localeCompare(b.name);
    });
  }

  function filterChats(){
    const q = (elSearch.value || "").toLowerCase().trim();

    let list = chats;

    if (activeTab === "unread") list = list.filter(c => (c.unread||0) > 0);
    if (activeTab === "pinned") list = list.filter(c => !!c.pinned);

    if (q){
      list = list.filter(c =>
        c.name.toLowerCase().includes(q) ||
        String(c.lastMessage||"").toLowerCase().includes(q)
      );
    }

    return sortChats(list);
  }

  function chatItemHTML(c){
    const active = (c.id === activeId);
    const pinIcon = c.pinned ? '<i class="mdi mdi-pin ef-pin" title="Pinned"></i>' : '';
    const unread = (c.unread||0) > 0 ? `<span class="ef-unread">${c.unread > 99 ? '99+' : c.unread}</span>` : '';
    const dot = c.online ? `<span class="ef-dot"></span>` : '';

    return `
      <div class="ef-chat-item ${active ? 'is-active' : ''}" data-id="${c.id}">
        <div class="ef-chat-avatar">
          ${initials(c.name)}
          ${dot}
        </div>

        <div class="ef-chat-meta">
          <div class="ef-chat-row1">
            <div class="ef-chat-name">${escapeHtml(c.name)}</div>
            <div class="ef-chat-time ${active ? '' : 'muted'}">${escapeHtml(c.time || '')}</div>
          </div>
          <div class="ef-chat-last">${escapeHtml(c.lastMessage || '')}</div>
        </div>

        <div class="ef-chat-right">
          ${pinIcon}
          ${unread}
        </div>

        <div class="ef-chat-actions">
          <button class="ef-chat-action-btn" data-action="pin" title="${c.pinned ? 'Unpin' : 'Pin'}">
            <i class="mdi ${c.pinned ? 'mdi-pin-off' : 'mdi-pin'}"></i>
          </button>
          <button class="ef-chat-action-btn" data-action="unread" title="Toggle Unread">
            <i class="mdi mdi-email-outline"></i>
          </button>
        </div>
      </div>
    `;
  }

  function renderList(){
    const list = filterChats();

    // grouped display: pinned section + all (kalau tab all)
    let pinned = list.filter(c => c.pinned);
    let other  = list.filter(c => !c.pinned);

    let html = "";

    if (activeTab === "all"){
      if (pinned.length){
        html += `<div class="ef-chat-section-title">Pinned</div>`;
        html += pinned.map(chatItemHTML).join("");
      }
      html += `<div class="ef-chat-section-title">Chats</div>`;
      html += other.map(chatItemHTML).join("");
      if (!pinned.length && !other.length) html += `<div class="text-muted p-3" style="font-size:13px;">No chats.</div>`;
    } else {
      html += list.map(chatItemHTML).join("");
      if (!list.length) html += `<div class="text-muted p-3" style="font-size:13px;">No chats.</div>`;
    }

    elList.innerHTML = html;
  }

  function renderHeader(chat){
    elPeerAvatar.textContent = initials(chat.name);
    elPeerName.textContent = chat.name;
    elPeerSub.textContent = chat.online ? "Online" : (chat.lastSeen || "Offline");
  }

  function bubbleHTML(msg){
    const isOut = msg.from === "me";
    const clsRow = isOut ? "out" : "in";
    const clsBub = isOut ? "out" : "in";
    const t = msg.time || "";
    return `
      <div class="ef-bubble-row ${clsRow}">
        <div class="ef-bubble ${clsBub}">
          ${escapeHtml(msg.text || "")}
          <span class="ef-time">${escapeHtml(t)}</span>
        </div>
      </div>
    `;
  }

  function renderMessages(chat){
    let html = `<div class="ef-day">Today</div>`;
    html += chat.messages.map(bubbleHTML).join("");
    elMsgs.innerHTML = html;
    elMsgs.scrollTop = elMsgs.scrollHeight;
  }

  function selectChat(id){
    const chat = chats.find(c => c.id === id);
    if (!chat) return;

    activeId = id;

    // mark as read
    if ((chat.unread||0) > 0){
      chat.unread = 0;
      persistChat(chat);
      updateGlobalUnread();
    }

    renderList();
    renderHeader(chat);
    renderMessages(chat);

    // enable composer
    elInput.disabled = false;
    elSend.disabled  = false;
    elInput.focus();
  }

  function togglePin(id){
    const chat = chats.find(c => c.id === id);
    if (!chat) return;
    chat.pinned = !chat.pinned;
    persistChat(chat);
    renderList();
  }

  function toggleUnread(id){
    const chat = chats.find(c => c.id === id);
    if (!chat) return;
    chat.unread = (chat.unread||0) > 0 ? 0 : 1;
    persistChat(chat);
    updateGlobalUnread();
    renderList();
  }

  function sendMessage(){
    const txt = (elInput.value || "").trim();
    if (!txt || !activeId) return;

    const chat = chats.find(c => c.id === activeId);
    if (!chat) return;

    const now = new Date();
    const time = now.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});

    chat.messages.push({from:"me", text: txt, time});
    chat.lastMessage = txt;
    chat.time = time;

    persistChat(chat);

    elInput.value = "";
    renderList();
    renderMessages(chat);
  }

  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, (m)=>({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
    }[m]));
  }

  // =========================
  // EVENTS
  // =========================
  elList.addEventListener("click", function(e){
    const item = e.target.closest(".ef-chat-item");
    if (!item) return;

    const id = item.getAttribute("data-id");
    const actBtn = e.target.closest("[data-action]");
    if (actBtn){
      const action = actBtn.getAttribute("data-action");
      if (action === "pin") togglePin(id);
      if (action === "unread") toggleUnread(id);
      return;
    }

    selectChat(id);
  });

  elTabs.forEach(t=>{
    t.addEventListener("click", function(){
      elTabs.forEach(x=>x.classList.remove("is-active"));
      t.classList.add("is-active");
      activeTab = t.getAttribute("data-tab");
      renderList();
    });
  });

  elSearch.addEventListener("input", function(){
    renderList();
  });

  elSend.addEventListener("click", sendMessage);
  elInput.addEventListener("keydown", function(e){
    if (e.key === "Enter"){
      e.preventDefault();
      sendMessage();
    }
  });

  // =========================
  // INIT
  // =========================
  updateGlobalUnread();
  renderList();

  // auto-open chat pertama (pinned paling atas jika ada)
  const first = sortChats(chats)[0];
  if (first) selectChat(first.id);

})();
</script>

<?php include "footer.php"; ?>
