function fn_ValForm(){
    var sMsg = "";
    var nama = document.getElementById("Nama").value;
    var umur = document.getElementById ("Umur").value;
    var email = document.getElementById ("Email").value;
    var kategori = document.getElementById ("Kategori").value;
    var Expression = ^[a-z0-9][a-z0-9_\.-]{0,}[a-z0-9]@[a-z0-9][a-z0-9_\.-]{0,}[a-z0-
                     9][\.][a-z0-9]{2,4}$/;

    if (document.getElementById("Nama").value == ""){
        sMsg += "\n* Anda belum mengisikan nama";
    }
    if (document.getElementById("Email").value == ""){
        sMsg += "\n* Anda belum mengisikan email";
    } else if (!Expression.test (email)){
        sMsg += "\n* Format email salah";
    }
    if (document.getElementById("Umur").value == ""){
        sMsg += "\n* Anda belum mengisikan umur";
    }
    if (document.getElementById("Jenis Acara").value == ""){
        sMsg += "\n* Anda belum mengisikan jenis acara";
    }

    if (sMsg != "") {
        alert("Peringatan:\n" + sMsg);
        return false;
    } else {
        return true;
    }

    if (umur <= 25) {
        if (sUmur == "U") {
            alert("");
        } else {
            alert 
        }
    }
}