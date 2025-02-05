<!DOCTYPE html>
<html lang="en" dir="ltr">

  <head>
    <meta charset="utf-8">
    <title>Video Chat</title>
  </head>

  <style>
    video {
      display: none;
    }

    canvas {
      border: 5px solid black;
      overflow: auto;

    }


  </style>
  <body>

    <video width="320" height="200" autoplay="true" id="video"></video>
    <canvas id="c" width="320" height="200" ></canvas>


  <script>

    var Users = {};

    var video = document.getElementById("video");
    var userMedia = navigator.mediaDevices;

    var canvas = document.getElementById("c");
    var c = canvas.getContext("2d");

   var ws = new WebSocket('ws://192.168.1.47:12322');

    ws.onopen = () => {

      console.log("connected");
    }

    video.onloadedmetadata = function() {
     setInterval(() => {

       c.drawImage(video, 0, 0, 320, 200);

       var message = canvas.toDataURL();

       if(ws.bufferedAmount == 0) {
         ws.send(message);
       }
  }, 100);
 };

    function loadImg(name, message) {

      var img = new Image;

          img.onload = () => {
            var cU = Users[name].getContext('2d');

            cU.drawImage(img, 0, 0);
          };

          img.src = message;
    }

    ws.onmessage = (message) => {
      if(message.data.substring(8, 15) == "connect") {

        getNewUser(message.data.substring(0, 8));

      } else if(message.data.substring(8, 18) == "disconnect") {

        removeUser(message.data.substring(0, 8));

      } else if(message.data.substring(0, 6) == "server") {

        generateUsers(message.data.split(","));

      } else {

        loadImg(message.data.substring(0, 8), message.data.substring(8, message.data.length));
      }
    }

    function generateUsers(names) {

      names.forEach((name) => {
        if(name != 'server') {

          console.log("added " + name);
          getNewUser(name);
        }
      });
    }

    function getNewUser(name) {

      var newEl = document.createElement('canvas');

      newEl.setAttribute('id', "c" + Object.keys(Users).length);
      newEl.setAttribute('width', 320);
      newEl.setAttribute('height', 200);

      document.body.appendChild(newEl);

      Users[name] = document.getElementById("c" + Object.keys(Users).length);
    }

    function removeUser(name) {

      Users[name].remove();
      delete Users[name];
    }

    if (userMedia) {
      userMedia.getUserMedia({ video: true }).then((stream) => {

       video.srcObject = stream;

      }).catch((error) => {

       console.log("error: " + error.message);
      });
    }


  </script>


  </body>
</html>
