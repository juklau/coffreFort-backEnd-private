<!DOCTYPE html>

<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title> Create New Password </title>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

        <link rel="stylesheet" href="style.css">
    </head>

    <body>
        <header>
            <nav class="navbar">                 
                <!-- Icon -->
                <div class="logo">
                    <img id="img-logo" src="img/logo.jpeg" alt="Logo"></img>
                </div>
            </nav>
        </header>

        <div class="my-5 row justify-content-center">
            <aside class="container-fluid col-10 col-sm-8 col-lg-4">
                <div class="row justify-content-center">
                
                    <!-- Sing in-->
                    <div class="frame">
                        <div class="text-center mb-4">
                            <div>
                                <H2 class="header-font H-font">Créer votre nouveau mot de passe</H2>
                            </div>
                        </div>

                        <!-- Form -->
                        <form class="mt-5" method="post" action="/auth/newPassword" id="newPasswordForm">
                            <p class="my-5 text-secondary text-center">
                                Veuillez saisir votre nouveau mot de passe ci-dessous
                            </p>
                            <!-- Inputs -->
                            <div class="row">                                  
                                <div class=" mb-4">
                                    <label class="text-secondary mb-2" for="newPassword">Nouveau mot de passe</label>
                                    <input type="password" class="form-control border rounded p-2"
                                    id="inputNewPassword" name="newPassword" required>
                                </div>
                                <ul class="ms-4 ps-sm-3 ps-lg-5 text-secondary">
                                    <li>Votre mot de passe doit contenir au moins 8 caractères</li>

                                    <!-- à implementer -->
                                    <!-- <li>Votre mot de passe doit contenir au moins 1 lettre majuscule</li>
                                    <li>Votre mot de passe doit contenir au moins 1 lettre minuscule</li>
                                    <li>Votre mot de passe doit contenir au moins 1 caractère spécial</li>
                                    <li>Votre mot de passe doit contenir au moins 1 chiffre</li> -->
                                </ul>

                                <div class="mt-4 mb-4">
                                    <label class="text-secondary mb-2" for="newPasswordConfirm">Confirmation du nouveau mot de passe</label>
                                    <input type="password" class="form-control border rounded p-2"
                                    id="inputNewPasswordConfirm" name="newPasswordConfirm" required>
                                </div>
                            </div>  

                            <!-- Button Log in-->
                            <div class="row justify-content-center mt-5">
                                <div class="d-grid">
                                    <button type="submit" class="button-primary">Changer mon mot de passe</button>
                                </div>
                            </div>
                        </form>                            
                    </div>
                </div>
            </aside>
        </div>
        
        <!-- il faut mettre dans singin.js!!!! -->
        <script src="forgotPassword.js"></script>
    </body>
</html>
