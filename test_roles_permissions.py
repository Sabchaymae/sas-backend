import time
from playwright.sync_api import sync_playwright

def test_roles_permissions():
    with sync_playwright() as p:
        # Lancer le navigateur
        browser = p.chromium.launch(headless=False) # Mettre à True pour ne pas voir la fenêtre
        page = browser.new_page()

        print("🚀 Démarrage du test Roles & Permissions...")

        # 1. Connexion
        page.goto("http://localhost:5173/login")
        page.fill("input[type='email']", "admin@oriotel.com")
        page.fill("input[type='password']", "Oriotel@2026")
        page.click("button[type='submit']")

        # Attendre le chargement du dashboard
        page.wait_for_url("**/users")
        print("✅ Connexion réussie.")

        # 2. Naviguer vers Roles & Permissions
        page.goto("http://localhost:5173/roles-permissions")
        page.wait_for_selector("text=Gestion des accès")
        print("✅ Page Roles & Permissions chargée.")

        # 3. Créer un nouveau rôle
        page.click("button:has-text('Nouveau rôle')")
        page.fill("input[placeholder='Ex: Responsable RH']", "Test Automation Role")
        page.click("button:has-text('Créer')")
        
        # Attendre que le rôle apparaisse dans la liste
        page.wait_for_selector("text=Test Automation Role")
        print("✅ Rôle 'Test Automation Role' créé avec succès.")

        # 4. Sélectionner le rôle et passer à l'étape suivante
        page.click("text=Test Automation Role")
        page.click("button:has-text('Suivant')")
        print("✅ Étape 2 (Collaborateurs) atteinte.")

        # 5. Passer à l'étape des permissions (sans sélectionner d'utilisateur spécifique pour tester le rôle complet)
        page.click("button:has-text('Configurer le(s) rôle(s) complet(s)')")
        print("✅ Étape 3 (Permissions) atteinte.")

        # 6. Modifier une permission (ex: cocher 'Création' pour 'Utilisateurs')
        # On cherche le bouton dans la matrice. Le sélecteur dépend de la structure exacte.
        # Ici on simule un clic sur un bouton de la matrice
        page.wait_for_selector("table")
        
        # On coche quelques cases au hasard pour le test
        checkboxes = page.query_selector_all("td button")
        if len(checkboxes) > 0:
            checkboxes[0].click() # Clic sur la première case
            print("✅ Permission modifiée dans la matrice.")

        # 7. Sauvegarder
        page.click("button:has-text('Sauvegarder les droits')")
        
        # Vérifier le retour à l'étape 1
        page.wait_for_selector("text=Étape 1 : Choisir un rôle")
        print("✅ Sauvegarde réussie et retour à l'étape 1.")

        print("🎉 Test terminé avec succès !")
        time.sleep(2)
        browser.close()

if __name__ == "__main__":
    try:
        test_roles_permissions()
    except Exception as e:
        print(f"❌ Le test a échoué : {e}")
