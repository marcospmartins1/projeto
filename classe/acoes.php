<?php
session_start();

//Importação dos arquivos de conexao e das classes
include 'conexao.php';
include 'class-usuario.php';

// Função para validação de login
if (isset($_POST['usuario']) && isset($_POST['senha'])) {
    $usuario = $_POST['usuario'];
    $senha = $_POST['senha'];

    $consulta = mysqli_query($conexao, "SELECT id, nome, data_nascimento, cargo, login, senha, foto FROM usuarios WHERE login = '" . mysqli_real_escape_string($conexao, $usuario) . "'");
    $dados = mysqli_fetch_assoc($consulta);
    $user = null;

    if ($dados != null) {
        $user = new Usuario($dados["id"], $dados["nome"], $dados["data_nascimento"], $dados["cargo"], $dados["login"], $dados["senha"], $dados["foto"]);
    }

    // Gerar o hash da senha fornecida pelo usuário
    $senha_hash = hash('sha256', $senha); // Gera o hash SHA-256 da senha

    // Verifique se o usuário foi encontrado e valide a senha
    if ($user != null && $user->senha === $senha_hash) {
        $_SESSION['user'] = $user;
        header('Location: ../pages/inicio.php');
        exit();
    } else {
        $_SESSION['msg'] = "Usuário ou senha incorretos!";
        header('Location: ../index.php');
        exit();
    }
}

//Verifica se a ação de logout foi solicitada
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
}

//Função para efetuar o Logout
function logout()
{
    session_start();
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// Função para inserir dados do usuário no banco de dados.
if (isset($_POST['create_usuario'])) {
    $nome = mysqli_real_escape_string($conexao, trim($_POST['nome']));
    $data_nascimento = mysqli_real_escape_string($conexao, trim($_POST['data_nascimento']));
    $cargo = mysqli_real_escape_string($conexao, trim($_POST['cargo']));
    $login = mysqli_real_escape_string($conexao, trim($_POST['login']));
    $senha = isset($_POST['senha']) ? mysqli_real_escape_string($conexao, hash('sha256', trim($_POST['senha']))) : '';

    // Verificar se o login já existe
    $verificaLogin = mysqli_query($conexao, "SELECT * FROM usuarios WHERE login = '$login'");
    if (mysqli_num_rows($verificaLogin) > 0) {
        // Retorna para o formulário com um alerta
        echo "<script>alert('Este login já está em uso. Por favor, escolha outro.'); window.location.href='../pages/forms/usuario-create.php';</script>";
        exit();
    }

    // Configuração do upload da imagem
    $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/projeto/src/uploads/user/"; // Caminho absoluto para a pasta de uploads
    $target_file = $target_dir . basename($_FILES["foto"]["name"]); // Caminho completo onde o arquivo vai ser salvo do banco de dados.

    $uploadOk = 1; // Variável para verificar se o upload foi bem-sucedido
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION)); // Obtém a extensão do arquivo (ex: jpg, png) e a converte para minúsculas

    // Verifica se o arquivo é uma imagem real
    $check = getimagesize($_FILES["foto"]["tmp_name"]);
    if ($check === false) {
        echo "<script>alert('Arquivo não é uma imagem.'); window.location.href='../pages/forms/usuario-create.php';</script>";
        $uploadOk = 0;
        exit();
    }

    // Verifica se o arquivo já existe
    if (file_exists($target_file)) {
        echo "<script>alert('Desculpe, essa foto já existe.'); window.location.href='../pages/forms/usuario-create.php';</script>";
        $uploadOk = 0;
        exit();
    }

    // Limitar o tamanho do arquivo (exemplo: 500KB)
    if ($_FILES["foto"]["size"] > 500000) {
        echo "<script>alert('Desculpe, seu arquivo é muito grande.'); window.location.href='../pages/forms/usuario-create.php';</script>";
        $uploadOk = 0;
        exit();
    }

    // Limitar os formatos de arquivo permitidos
    if (!in_array($imageFileType, ['jpg', 'jpeg', 'png'])) {
        echo "<script>alert('Desculpe, somente arquivos JPG, JPEG, PNG são permitidos.'); window.location.href='../pages/forms/usuario-create.php';</script>";
        $uploadOk = 0;
        exit();
    }

    // Verifica se $uploadOk está definido como 0 por um erro
    if ($uploadOk == 0) {
        echo "<script>alert('Desculpe, seu arquivo não foi enviado.'); window.location.href='../pages/forms/usuario-create.php';</script>";
    } else {
        // Tenta mover o arquivo para o diretório de destino
        if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
            $caminho_imagem = "user/" . basename($_FILES["foto"]["name"]); // Define o caminho da imagem como "uploads/" seguido do nome do arquivo enviado
        } else {
            $_SESSION['mensagem'] = "Desculpe, houve um erro ao enviar seu arquivo.";
            $caminho_imagem = null;
        }
    }

    // Inserir o usuário no banco de dados
    $sql = "INSERT INTO usuarios (nome, data_nascimento, cargo, login, senha, foto) 
            VALUES ('$nome', '$data_nascimento', '$cargo', '$login', '$senha', '$caminho_imagem')";

    mysqli_query($conexao, $sql);

    if (mysqli_affected_rows($conexao) > 0) {
        $_SESSION['mensagem'] = 'Usuário criado com sucesso';
    } else {
        $_SESSION['mensagem'] = 'Usuário não foi criado: ' . mysqli_error($conexao);
    }

    header('Location: ../pages/usuario.php');
    exit;
}

// Função para atualizar dados do usuário no banco de dados.
if (isset($_POST['update_usuario'])) {
    $usuario_id = mysqli_real_escape_string($conexao, $_POST['usuario_id']);
    $nome = mysqli_real_escape_string($conexao, trim($_POST['nome']));
    $data_nascimento = mysqli_real_escape_string($conexao, trim($_POST['data_nascimento']));
    $cargo = mysqli_real_escape_string($conexao, trim($_POST['cargo']));
    $login = mysqli_real_escape_string($conexao, trim($_POST['login']));
    $senha = isset($_POST['senha']) ? mysqli_real_escape_string($conexao, trim($_POST['senha'])) : '';

    // Caminho da imagem que será usado no banco de dados
    $caminho_imagem = "";

    // Configuração do upload da imagem
    $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/projeto/src/uploads/user/"; // Caminho absoluto para a pasta de uploads
    $target_file = $target_dir . basename($_FILES["foto"]["name"]); // Caminho completo onde o arquivo vai ser salvo

    $uploadOk = 1; // Variável para verificar se o upload foi bem-sucedido
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION)); // Obtém a extensão do arquivo e converte para minúsculas

    // Verificar se foi enviada uma nova foto
    if (!empty($_FILES['foto']['name'])) {
        // Verifica se o arquivo é uma imagem real
        $check = getimagesize($_FILES["foto"]["tmp_name"]);
        if ($check === false) {
            echo "<script>alert('Arquivo não é uma imagem.'); window.location.href='../pages/forms/usuario-create.php';</script>";
            $uploadOk = 0;
            exit();
        }

        // Verifica se o arquivo já existe
        if (file_exists($target_file)) {
            echo "<script>alert('Desculpe, essa foto já existe.'); window.location.href='../pages/forms/usuario-create.php';</script>";
            $uploadOk = 0;
            exit();
        }

        // Limitar o tamanho do arquivo (exemplo: 500KB)
        if ($_FILES["foto"]["size"] > 500000) {
            echo "<script>alert('Desculpe, seu arquivo é muito grande.'); window.location.href='../pages/forms/usuario-create.php';</script>";
            $uploadOk = 0;
            exit();
        }

        // Limitar os formatos de arquivo permitidos
        if (!in_array($imageFileType, ['jpg', 'jpeg', 'png'])) {
            echo "<script>alert('Desculpe, somente arquivos JPG, JPEG, PNG são permitidos.'); window.location.href='../pages/forms/usuario-create.php';</script>";
            $uploadOk = 0;
            exit();
        }

        // Tenta mover o arquivo para o diretório de destino se todas as verificações passarem
        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
                $caminho_imagem = "user/" . basename($_FILES["foto"]["name"]);
            } else {
                echo "<script>alert('Desculpe, seu arquivo não foi enviado.'); window.location.href='../pages/forms/usuario-create.php';</script>";
                exit;
            }
        } else {
            // Se o upload falhar, redireciona
            header('Location: ../pages/forms/usuario-edit.php?id=' . $usuario_id);
            exit;
        }
    }

    // Se não houver nova imagem, mantenha a anterior
    if (empty($caminho_imagem)) {
        $sql_current = "SELECT foto FROM usuarios WHERE id='$usuario_id'"; // Query para obter a imagem atual
        $result_current = mysqli_query($conexao, $sql_current); // Executa a query
        if ($result_current && mysqli_num_rows($result_current) > 0) {
            $current_data = mysqli_fetch_assoc($result_current); // Obtém os dados atuais
            $caminho_imagem = $current_data['foto']; // Mantém a imagem atual se não houver nova
        }
    }

    // Atualizar os dados no banco de dados
    $sql = "UPDATE usuarios SET nome='$nome', data_nascimento='$data_nascimento', cargo='$cargo', login='$login'";

    //Caso o campo de senha fique vazio, a senha antiga prevalece
    if (!empty($senha)) {
        // Usar SHA2 para hash da senha
        $senha_hash = hash('sha256', $senha);
        $sql .= ", senha='$senha_hash'";
    }

    $sql .= ", foto='$caminho_imagem' WHERE id='$usuario_id'";

    // Executar a query de atualização
    if (mysqli_query($conexao, $sql)) {
        $_SESSION['mensagem'] = 'Usuário atualizado com sucesso';
    } else {
        $_SESSION['mensagem'] = 'Erro ao atualizar usuário: ' . mysqli_error($conexao);
    }

    header('Location: ../pages/usuario.php');
    exit;
}

//Função para deletar dados do usuário no banco de dados.
if (isset($_POST['delete_usuario'])) {
    $usuario_id = mysqli_real_escape_string($conexao, $_POST['delete_usuario']);

    $sql = "DELETE FROM usuarios WHERE id = '$usuario_id'";
    mysqli_query($conexao, $sql);
    if (mysqli_affected_rows($conexao) > 0) {
        $_SESSION['message'] = 'Usuário deletado com sucesso';
    } else {
        $_SESSION['message'] = 'Usuário não foi deletado';
    }

    header('Location: ../pages/fornecedor.php');
    exit;
}

//Função para inserir dados do fornecedor no banco de dados.
if (isset($_POST['create_fornecedor'])) {
    $nome = mysqli_real_escape_string($conexao, trim($_POST['nome']));
    $cnpj = mysqli_real_escape_string($conexao, trim($_POST['cnpj']));
    $telefone = mysqli_real_escape_string($conexao, trim($_POST['telefone']));
    $descricao = mysqli_real_escape_string($conexao, trim($_POST['descricao']));
    $status = mysqli_real_escape_string($conexao, trim($_POST['status']));
    $sql = "INSERT INTO fornecedores (nome, cnpj, telefone, descricao, status) VALUES ('$nome', '$cnpj', '$telefone', '$descricao', '$status')";
    mysqli_query($conexao, $sql);
    if (mysqli_affected_rows($conexao) > 0) {
        $_SESSION['mensagem'] = 'Fornecedor criado com sucesso';
    } else {
        $_SESSION['mensagem'] = 'Fornecedor não foi criado' . mysqli_error($conexao);
    }

    header('Location: ../pages/fornecedor.php');
    exit;
}

//Função para atualizar dados do fornecedor no banco de dados.
if (isset($_POST['update_fornecedor'])) {
    $fornecedor_id = mysqli_real_escape_string($conexao, $_POST['fornecedor_id']);
    $nome = mysqli_real_escape_string($conexao, trim($_POST['nome']));
    $cnpj = mysqli_real_escape_string($conexao, trim($_POST['cnpj']));
    $telefone = mysqli_real_escape_string($conexao, trim($_POST['telefone']));
    $descricao = mysqli_real_escape_string($conexao, trim($_POST['descricao']));
    $status = mysqli_real_escape_string($conexao, trim($_POST['status']));
    $sql = "UPDATE fornecedores SET nome = '$nome', cnpj = '$cnpj', telefone = '$telefone', descricao = '$descricao', status = '$status' WHERE id = '$fornecedor_id'";
    mysqli_query($conexao, $sql);
    if (mysqli_affected_rows($conexao) > 0) {
        $_SESSION['mensagem'] = 'Fornecedor atualizado com sucesso';
    } else {
        $_SESSION['mensagem'] = 'Fornecedor não foi atualizado' . mysqli_error($conexao);
    }

    header('Location: ../pages/fornecedor.php');
    exit;
}

//Função para deletar dados do usuário no banco de dados.
if (isset($_POST['delete_fornecedor'])) {
    $fornecedor_id = mysqli_real_escape_string($conexao, $_POST['delete_fornecedor']);

    $sql = "DELETE FROM fornecedores WHERE id = '$fornecedor_id'";
    mysqli_query($conexao, $sql);
    if (mysqli_affected_rows($conexao) > 0) {
        $_SESSION['message'] = 'Fornecedor deletado com sucesso';
    } else {
        $_SESSION['message'] = 'Fornecedor não foi deletado' . mysqli_error($conexao);
    }

    header('Location: ../pages/fornecedor.php');
    exit;
}

//Função para inserir dados do cliente no banco de dados.
if (isset($_POST['create_cliente'])) {
    $nome = mysqli_real_escape_string($conexao, trim($_POST['nome']));
    $data_nascimento = mysqli_real_escape_string($conexao, trim($_POST['data_nascimento']));
    $cpf = mysqli_real_escape_string($conexao, trim($_POST['cpf']));
    $telefone = mysqli_real_escape_string($conexao, trim($_POST['telefone']));
    $sql = "INSERT INTO clientes (nome, data_nascimento, cpf, telefone) VALUES ('$nome', '$data_nascimento', '$cpf', '$telefone')";
    mysqli_query($conexao, $sql);
    if (mysqli_affected_rows($conexao) > 0) {
        $_SESSION['mensagem'] = 'Cliente criado com sucesso';
    } else {
        $_SESSION['mensagem'] = 'Cliente não foi criado' . mysqli_error($conexao);
    }

    header('Location: ../pages/cliente.php');
    exit;
}

//Função para atualizar dados do cliente no banco de dados.
if (isset($_POST['update_cliente'])) {
    $cliente_id = mysqli_real_escape_string($conexao, $_POST['cliente_id']);
    $nome = mysqli_real_escape_string($conexao, trim($_POST['nome']));
    $data_nascimento = mysqli_real_escape_string($conexao, trim($_POST['data_nascimento']));
    $cpf = mysqli_real_escape_string($conexao, trim($_POST['cpf']));
    $telefone = mysqli_real_escape_string($conexao, trim($_POST['telefone']));

    $sql = "UPDATE clientes SET nome = '$nome', data_nascimento = '$data_nascimento', cpf = '$cpf', telefone = '$telefone' WHERE id = '$cliente_id'";

    mysqli_query($conexao, $sql);
    if (mysqli_affected_rows($conexao) > 0) {
        $_SESSION['mensagem'] = 'Cliente atualizado com sucesso';
    } else {
        $_SESSION['mensagem'] = 'Cliente não foi atualizado' . mysqli_error($conexao);
    }

    header('Location: ../pages/cliente.php');
    exit;
}

//Função para deletar dados do cliente no banco de dados.
if (isset($_POST['delete_cliente'])) {
    $cliente_id = mysqli_real_escape_string($conexao, $_POST['delete_cliente']);

    $sql = "DELETE FROM clientes WHERE id = '$cliente_id'";
    mysqli_query($conexao, $sql);
    if (mysqli_affected_rows($conexao) > 0) {
        $_SESSION['message'] = 'Cliente deletado com sucesso';
    } else {
        $_SESSION['message'] = 'Cliente não foi deletado' . mysqli_error($conexao);
    }

    header('Location: ../pages/cliente.php');
    exit;
}

//Função para inserir dados do produto no banco de dados.
if (isset($_POST['create_produto'])) {
    $nome = mysqli_real_escape_string($conexao, trim($_POST['nome']));
    $cor = mysqli_real_escape_string($conexao, trim($_POST['cor']));
    $genero = mysqli_real_escape_string($conexao, trim($_POST['genero']));
    $tamanho = mysqli_real_escape_string($conexao, trim($_POST['tamanho']));
    $quantidade = mysqli_real_escape_string($conexao, trim($_POST['quantidade']));
    $preco = mysqli_real_escape_string($conexao, trim($_POST['preco']));
    $fornecedor_id = mysqli_real_escape_string($conexao, $_POST['fornecedor']);

    // Configuração do upload da imagem
    $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/projeto/src/uploads/";  // Caminho absoluto para a pasta de uploads
    $target_file = $target_dir . basename($_FILES["foto"]["name"]);  // Caminho completo onde o arquivo vai ser salvo do banco de dados.

    $uploadOk = 1; // Variável para verificar se o upload foi bem-sucedido
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION)); // Obtém a extensão do arquivo (ex: jpg, png) e a converte para minúsculas

    // Verifica se o arquivo é uma imagem real ou um fake
    $check = getimagesize($_FILES["foto"]["tmp_name"]);
    if ($check === false) {
        echo "<script>alert('Arquivo não é uma imagem.'); window.location.href='../pages/forms/produto-create.php';</script>";
        $uploadOk = 0;
        exit();
    }

    // Verifica se o arquivo já existe
    if (file_exists($target_file)) {
        echo "<script>alert('Desculpe, essa foto já existe.'); window.location.href='../pages/forms/produto-create.php';</script>";
        $uploadOk = 0;
        exit();
    }

    // Limitar o tamanho do arquivo (exemplo: 500KB)
    if ($_FILES["foto"]["size"] > 500000) {
        echo "<script>alert('Desculpe, seu arquivo é muito grande.'); window.location.href='../pages/forms/produto-create.php';</script>";
        $uploadOk = 0;
        exit();
    }

    // Limitar os formatos de arquivo permitidos
    if (!in_array($imageFileType, ['jpg', 'jpeg', 'png'])) {
        echo "<script>alert('Desculpe, somente arquivos JPG, JPEG, PNG são permitidos.'); window.location.href='../pages/forms/produto-create.php';</script>";
        $uploadOk = 0;
        exit();
    }

    // Verifica se $uploadOk está definido como 0 por um erro
    if ($uploadOk == 0) {
        echo "<script>alert('Desculpe, seu arquivo não foi enviado.'); window.location.href='../pages/forms/produto-create.php';</script>";
    } else {
        // Tenta mover o arquivo para o diretório de destino
        if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
            echo "O arquivo " . htmlspecialchars(basename($_FILES["foto"]["name"])) . " foi enviado.";

            $caminho_imagem = "uploads/" . basename($_FILES["foto"]["name"]); // Define o caminho da imagem como "uploads/" seguido do nome do arquivo enviado
        } else {
            $_SESSION['mensagem'] = "Desculpe, houve um erro ao enviar seu arquivo.";
            $caminho_imagem = null;
        }
    }

    // Inserir o produto no banco de dados
    $sql = "INSERT INTO produtos (nome, cor, genero, tamanho, quantidade, preco, fornecedor_id, imagem) 
            VALUES ('$nome', '$cor', '$genero', '$tamanho', '$quantidade', '$preco', '$fornecedor_id', '$caminho_imagem')";

    mysqli_query($conexao, $sql);

    if (mysqli_affected_rows($conexao) > 0) {
        $_SESSION['mensagem'] = 'Produto criado com sucesso';
    } else {
        $_SESSION['mensagem'] = 'Erro ao criar produto' . mysqli_error($conexao);
    }

    header('Location: ../pages/produto.php');
    exit;
}

//Função para atualizados dados do produto no banco de dados.
if (isset($_POST['update_produto'])) {
    $produto_id = mysqli_real_escape_string($conexao, $_POST['produto_id']);
    $nome = mysqli_real_escape_string($conexao, trim($_POST['nome']));
    $cor = mysqli_real_escape_string($conexao, trim($_POST['cor']));
    $genero = mysqli_real_escape_string($conexao, trim($_POST['genero']));
    $tamanho = mysqli_real_escape_string($conexao, trim($_POST['tamanho']));
    $quantidade = mysqli_real_escape_string($conexao, trim($_POST['quantidade']));
    $preco = mysqli_real_escape_string($conexao, trim($_POST['preco']));
    $fornecedor_id = mysqli_real_escape_string($conexao, $_POST['fornecedor']);

    // Caminho da imagem que será usado no banco de dados
    $caminho_imagem = "";

    // Configuração do upload da imagem
    $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/projeto/src/uploads/";  // Caminho absoluto para a pasta de uploads
    $target_file = $target_dir . basename($_FILES["foto"]["name"]);  // Caminho completo onde o arquivo vai ser salvo

    $uploadOk = 1; // Variável para verificar se o upload foi bem-sucedido
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION)); // Obtém a extensão do arquivo e converte para minúsculas

    // Verificar se foi enviada uma nova foto
    if (!empty($_FILES['foto']['name'])) {
        // Verifica se o arquivo é uma imagem real
        $check = getimagesize($_FILES["foto"]["tmp_name"]);
        if ($check !== false) {
            // Verifica se o arquivo já existe no diretório de uploads
            if (file_exists($target_file)) {
                echo "<script>alert('Desculpe, essa foto já existe.'); window.location.href='../pages/forms/produto-create.php';</script>";
                $uploadOk = 0;
                exit();
            }

            // Limitar o tamanho do arquivo (exemplo: 500KB)
            if ($_FILES["foto"]["size"] > 500000) {
                echo "<script>alert('Desculpe, seu arquivo é muito grande.'); window.location.href='../pages/forms/produto-create.php';</script>";
                $uploadOk = 0;
                exit();
            }

            // Limitar os formatos de arquivo permitidos
            if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
                echo "<script>alert('Desculpe, somente arquivos JPG, JPEG, PNG são permitidos.'); window.location.href='../pages/forms/produto-create.php';</script>";
                $uploadOk = 0;
                exit();
            }

            // Verifica se $uploadOk está definido como 0 por um erro
            if ($uploadOk == 0) {
                echo "<script>alert('Desculpe, seu arquivo não foi enviado.'); window.location.href='../pages/forms/produto-create.php';</script>";
            } else {
                // Tenta mover o arquivo para o diretório de destino
                if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
                    echo "O arquivo " . htmlspecialchars(basename($_FILES["foto"]["name"])) . " foi enviado.";
                    $caminho_imagem = "uploads/" . basename($_FILES["foto"]["name"]); // Caminho da imagem para o banco de dados
                } else {
                    echo "Desculpe, houve um erro ao enviar seu arquivo.";
                    exit;
                }
            }
        } else {
            echo "Arquivo não é uma imagem válida.";
            exit;
        }
    }

    // Se não houver nova imagem, mantenha a anterior
    if (empty($caminho_imagem)) {
        $sql_current = "SELECT imagem FROM produtos WHERE id='$produto_id'"; // Query para obter a imagem atual
        $result_current = mysqli_query($conexao, $sql_current); // Executa a query
        if ($result_current && mysqli_num_rows($result_current) > 0) {
            $current_data = mysqli_fetch_assoc($result_current); // Obtém os dados atuais
            $caminho_imagem = $current_data['imagem']; // Mantém a imagem atual se não houver nova
        }
    }

    // Atualizar os dados no banco de dados
    $sql = "UPDATE produtos SET nome='$nome', cor='$cor', genero='$genero', tamanho='$tamanho', quantidade='$quantidade', 
			preco='$preco', fornecedor_id='$fornecedor_id', imagem='$caminho_imagem' WHERE id='$produto_id'";

    // Executar a query de atualização
    if (mysqli_query($conexao, $sql)) {
        $_SESSION['mensagem'] = 'Produto atualizado com sucesso';
    } else {
        $_SESSION['mensagem'] = 'Erro ao atualizar produto' . mysqli_error($conexao);
    }

    header('Location: ../pages/produto.php');
    exit;
}

//Função para deletar dados do produto no banco de dados.
if (isset($_POST['delete_produto'])) {
    $produto_id = mysqli_real_escape_string($conexao, $_POST['delete_produto']);

    //Obtem caminho da imagem do produto
    $sql_current = "SELECT imagem FROM produtos WHERE id='$produto_id'"; // Query para obter a imagem atual
    $result_current = mysqli_query($conexao, $sql_current); // Executa a query

    if ($result_current && mysqli_num_rows($result_current) > 0) {
        $current_data = mysqli_fetch_assoc($result_current);
        $caminho_imagem = $_SERVER['DOCUMENT_ROOT'] . "/projeto/src/" . $current_data['imagem']; // Caminho completo da imagem a ser deletada

        // Tenta deletar o arquivo de imagem, se existir
        if (file_exists($caminho_imagem)) {
            unlink($caminho_imagem); // Remove o arquivo de imagem do servidor
        }
    }

    // Deletar o produto do banco de dados
    $sql = "DELETE FROM produtos WHERE id = '$produto_id'";
    mysqli_query($conexao, $sql);

    if (mysqli_affected_rows($conexao) > 0) {
        $_SESSION['mensagem'] = 'Produto deletado com sucesso';
    } else {
        $_SESSION['mensagem'] = 'Produto não foi deletado' . mysqli_error($conexao);
    }

    header('Location: ../pages/produto.php');
    exit;
}