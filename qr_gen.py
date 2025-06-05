import qrcode        #https://yandex.ru/video/preview/10580817416444401780
import secrets
import mysql.connector

def gen_qr():
    token = secrets.token_urlsafe(32)
    
    site = f"https://yashka37.ru/login.php?qt={token}"      #qr.add_data(f"https://yashka37.ru/login.php?qr={token}")  
    path = f"/var/www/html/qr/{token}.png"              #/var/www/html/qr/{ТОКЕНС СЮДА}.PNG
    
    qr = qrcode.QRCode(
        version=1,
        error_correction=qrcode.constants.ERROR_CORRECT_L,
        box_size=12,
        border=2.5,
    )
    
    qr.add_data(site)
    qr.make(fit=True)
    img = qr.make_image(fill='black', back_color='white')

    try:                        #БЫЛО img.save(path)  return path
        img.save(path)    # 1.1 БЫЛО   img.save(path)  print(path)   return path
    except Exception as a:
        print(f"Ошибка: {a}")
        return None
        
    try:    
      conn = mysql.connector.connect(
        host="localhost",
        user="root",
        password="xxXX1234",
        database="resourses"
      )  #resourses
      
      cursor = conn.cursor()    #https://habr.com/ru/articles/321510/
      cursor.execute("""
      INSERT INTO qr_tokens (token, status, created, expire)
      VALUES (%s, 'pending', NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE))
      """, (token,))
      
      conn.commit()
      cursor.close()
      conn.close()
      
    except Exception as b:
      print(f"Ошибка БД: {b}")
      return None
      
    print(token)  #токен перехват PHP
    return path
    
gen_qr()