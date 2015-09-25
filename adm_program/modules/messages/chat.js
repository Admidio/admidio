/**
 * Name: Admidio Chat Engine
 */

var instanse = false;
var state;
var mes;

function Chat() {
    this.getState = getStateOfChat;
    this.update = updateChat;
    this.send = sendChat;
}

// gets the state of the chat
function getStateOfChat() {
    state = 0;
}

// Updates the chat
function updateChat() {
    if(!instanse)
    {
        instanse = true;
        $.ajax({
            type: "POST",
            url: "process.php",
            data: {
                "function": "update",
                "state": state
            },
            dataType: "json",
            success: function(data) {
                if (data.text)
                {
                    for (var i = 0; i < data.text.length; i++)
                    {
                        $("#chat-area").append($("<p>" + data.text[i] + "</p>"));
                    }
                    document.getElementById("chat-area").scrollTop = document.getElementById("chat-area").scrollHeight;
                }
                instanse = false;
                state = data.state;
            }
        });
    }
}

// send the message
function sendChat(message) {
    updateChat();
    $.ajax({
        type: "POST",
        url: "process.php",
        data: {
            "function": "send",
            "message": message,
            "state": state
        },
        dataType: "json",
        success: function(data) {
            updateChat();
        }
    });
}
