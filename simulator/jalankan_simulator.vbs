' jalankan_simulator.vbs
' Menjalankan simulator_sensor_background.py di background TANPA jendela
' console, menggunakan pythonw.exe. Karena tidak ada console yang menempel,
' menutup CMD/PowerShell/terminal manapun TIDAK akan mematikan simulator ini.
'
' Cara pakai:
' 1. Taruh file ini SATU FOLDER dengan simulator_sensor_background.py
' 2. Double-click file ini (atau klik kanan > Open)
' 3. Simulator akan langsung jalan di belakang layar (tidak ada jendela muncul)
' 4. Cek file simulator.log di folder yang sama untuk melihat aktivitasnya
' 5. Untuk MENGHENTIKAN: buka Task Manager > cari proses "pythonw.exe" > End Task
'    (atau cari task "python" lalu End Task pada proses yang menjalankan
'    simulator_sensor_background.py)

Set objShell = CreateObject("WScript.Shell")
strPath = objShell.CurrentDirectory
objShell.Run "pythonw.exe """ & strPath & "\simulator_sensor_background.py""", 0, False