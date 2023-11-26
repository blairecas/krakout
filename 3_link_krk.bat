call create_overlay.bat
rt11 del ld0:krk.sav
rt11 del ld0:krkbin.dat
rt11 copy krkbin.dat ld0:krkbin.dat
rt11 copy krk.sav ld0:krk.sav
"c:\Program Files\7-Zip\7z.exe" a -tzip release/krk.zip krk.sav krkbin.dat
