// public/firebase-messaging-sw.js

importScripts('https://www.gstatic.com/firebasejs/10.8.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.8.1/firebase-messaging-compat.js');

// åˆå§‹åŒ– Firebaseï¼ˆç¡®ä¿å’Œä¸»é¡¹ç›®é…ç½®ä¸€è‡´ï¼‰
firebase.initializeApp({
	apiKey: "AIzaSyBBpq7izLaLrm7G_T-Sb6mvyixpfq2Awr8",
	authDomain: "rajalottery-2334e.firebaseapp.com",
	projectId: "rajalottery-2334e",
	storageBucket: "rajalottery-2334e.firebasestorage.app",
	messagingSenderId: "1095002278096",
	appId: "1:1095002278096:web:51a63e4a49c85a5e6036d5",
	measurementId: "G-89DY48B60N"
});

// èŽ·å– messaging å®žä¾‹
const messaging = firebase.messaging();

// ç›‘å¬åŽå°æŽ¨é€é€šçŸ¥
messaging.onBackgroundMessage(function(payload) {
	console.log('[firebase-messaging-sw.js] æ”¶åˆ°åŽå°æ¶ˆæ¯: ', payload);
	const { title, body, image } = payload.notification;

	self.registration.showNotification(title, {
		body,
		icon: image || '/logo.png', // å¯æ›¿æ¢ä¸ºä½ çš„å›¾æ ‡
	});
});

// æŽ¥æ”¶å‰å°å‘æ¥çš„æ¶ˆæ¯å±•ç¤ºé€šçŸ¥
self.addEventListener('message', (event) => {
	if (event.data?.type === 'SHOW_NOTIFICATION') {
		const { title, body, image } = event.data.payload
		self.registration.showNotification(title, {
			body,
			image,
			icon: '/logo.png', // å¯è‡ªå®šä¹‰å›¾æ ‡
		})
	}
})