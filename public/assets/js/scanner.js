/**
 * Leitor de QR Code via câmera do dispositivo, usando a biblioteca jsQR.
 * Funciona em qualquer celular/tablet/notebook com câmera e navegador moderno,
 * sem precisar de leitor de código de barras dedicado.
 *
 * Uso no HTML da página:
 *   <video id="leitor-video" playsinline></video>
 *   <canvas id="leitor-canvas" style="display:none"></canvas>
 *   <div id="leitor-resultado"></div>
 *   <script src="https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.js"></script>
 *   <script src="/assets/js/scanner.js"></script>
 *   <script>
 *     iniciarLeitorQr('leitor-video', 'leitor-canvas', function(texto) {
 *       // texto = conteúdo decodificado do QR (ex: "LOC|AR01-CR01-PR01-N01-P01")
 *     });
 *   </script>
 */

let _leitorStream = null;
let _leitorAtivo = false;

function iniciarLeitorQr(videoElId, canvasElId, aoDetectar) {
  const video = document.getElementById(videoElId);
  const canvas = document.getElementById(canvasElId);
  const ctx = canvas.getContext('2d', { willReadFrequently: true });

  if (typeof jsQR === 'undefined') {
    alert('Biblioteca jsQR não carregou. Verifique a conexão com a internet.');
    return;
  }

  navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    .then(function (stream) {
      _leitorStream = stream;
      _leitorAtivo = true;
      video.srcObject = stream;
      video.setAttribute('playsinline', true);
      video.play();
      requestAnimationFrame(() => tick(video, canvas, ctx, aoDetectar));
    })
    .catch(function (err) {
      alert('Não foi possível acessar a câmera: ' + err.message);
    });
}

function pararLeitorQr() {
  _leitorAtivo = false;
  if (_leitorStream) {
    _leitorStream.getTracks().forEach(t => t.stop());
    _leitorStream = null;
  }
}

function tick(video, canvas, ctx, aoDetectar) {
  if (!_leitorAtivo) return;

  if (video.readyState === video.HAVE_ENOUGH_DATA) {
    canvas.height = video.videoHeight;
    canvas.width = video.videoWidth;
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const codigo = jsQR(imageData.data, imageData.width, imageData.height, {
      inversionAttempts: 'dontInvert',
    });

    if (codigo && codigo.data) {
      pararLeitorQr();
      aoDetectar(codigo.data);
      return;
    }
  }
  requestAnimationFrame(() => tick(video, canvas, ctx, aoDetectar));
}

/**
 * Gera um QR Code visual dentro de um elemento, usando a biblioteca qrcodejs.
 * Requer: <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
 */
function gerarQrEm(elementoId, texto, tamanho) {
  tamanho = tamanho || 120;
  const el = document.getElementById(elementoId);
  el.innerHTML = '';
  new QRCode(el, {
    text: texto,
    width: tamanho,
    height: tamanho,
    correctLevel: QRCode.CorrectLevel.M,
  });
}
