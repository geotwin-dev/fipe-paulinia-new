# 📸 Sistema de Visualização de Imagens Aéreas

## ✅ Implementação Completa

Este sistema permite visualizar imagens aéreas no mapa com coordenadas geográficas através de pinpoints (marcadores) clicáveis.

## 🎯 Funcionalidades Implementadas

### 1. **Checkbox de Controle**
- Localização: Menu de camadas no `index_3.php`
- ID: `chkImagensAereas`
- Label: "Imagens Aéreas"
- Estado inicial: **Desmarcado** (camada oculta por padrão)

### 2. **Marcadores no Mapa**
- **Ícone**: 📷 (emoji de câmera)
- **Estilo**: 24px, com sombra drop-shadow
- **Posicionamento**: Latitude e longitude de cada imagem
- **Z-index**: 100 (sempre visível sobre outros elementos)

### 3. **InfoWindow Interativo**
Ao clicar em um marcador, abre um InfoWindow com:

#### a) Loading Inicial
- Spinner do Bootstrap
- Mensagem: "Carregando imagem..."

#### b) Conteúdo após Carregamento
- **Título**: Nome do arquivo da imagem
- **Imagem**: 500px de largura (proporção automática)
- **Borda**: 2px solid #ddd com border-radius
- **Informações exibidas**:
  - Latitude
  - Longitude  
  - Altitude (se disponível)

#### c) Imagem Clicável
Ao clicar na imagem:
- Mostra o **caminho completo** no console
- Exibe alert com o caminho
- Log estruturado com:
  - 📂 Caminho completo
  - 📄 Nome do arquivo
  - 🗺️ Coordenadas (lat, lng, altitude)

### 4. **Tratamento de Erros**
- Validação de coordenadas
- Tratamento de imagens não encontradas
- Mensagens de erro descritivas
- Ícone de aviso visual

## 📁 Arquivos Modificados

### 1. `framework.js`
- **Linhas modificadas**: 2880-3082
- **Funções adicionadas**:
  - `carregarImagensAereas2()` - Cria os marcadores
  - `abrirInfoWindowImagemAerea()` - Exibe o InfoWindow com a imagem

### 2. `index_3.php`
- Adicionado checkbox `#chkImagensAereas`
- Adicionado evento de change para controlar visibilidade

### 3. `buscarImagemAerea.php` (NOVO)
- Script PHP para servir imagens de fora do XAMPP
- Validação de tipos MIME
- Segurança contra directory traversal
- Cache otimizado (1 ano)

## 🔧 Como Funciona

### Fluxo de Dados

```
1. Página carrega → carregarImagensAereas(quadricula)
                    ↓
2. Busca arquivo JSON com caminhos
                    ↓
3. carregarImagensAereas2(params, quadricula, caminhoUndistorted)
                    ↓
4. Lê arquivo TXT via buscarImagensAereas.php
                    ↓
5. Cria marcadores para cada imagem
                    ↓
6. Adiciona à camada 'imagens_aereas' (oculta)
                    ↓
7. Usuário marca checkbox → Marcadores aparecem
                    ↓
8. Usuário clica em marcador → abrirInfoWindowImagemAerea()
                    ↓
9. Loading aparece
                    ↓
10. AJAX busca imagem via buscarImagemAerea.php
                    ↓
11. Imagem carrega e substitui o loading
                    ↓
12. Usuário clica na imagem → Caminho no console
```

## 🎨 Estrutura da Camada

```javascript
arrayCamadas['imagens_aereas'] = [
    {
        // Advanced Marker Element
        position: { lat: ..., lng: ... },
        dadosImagem: {
            imageName: "DSC_0001.jpg",
            latitude: -22.xxxxx,
            longitude: -47.xxxxx,
            altitude: 600  // opcional
        },
        caminhoImagem: "D:\\path\\to\\image.jpg"
    },
    // ... mais marcadores
]
```

## 🔐 Segurança

### buscarImagemAerea.php
- ✅ Remoção de `..` e `\0` do caminho
- ✅ Validação de existência do arquivo
- ✅ Verificação de tipo MIME
- ✅ Lista branca de tipos permitidos
- ✅ Headers de cache apropriados

### Tipos de Imagem Permitidos
- image/jpeg
- image/jpg
- image/png
- image/gif
- image/webp
- image/bmp
- image/tiff

## 📊 Exemplo de Arquivo TXT Lido

```txt
imageName latitude longitude altitude
DSC_0001.jpg -22.7543210 -47.1234560 620.5
DSC_0002.jpg -22.7545678 -47.1237890 618.2
DSC_0003.jpg -22.7548901 -47.1241234 615.8
```

## 🎯 Controle de Visibilidade

O checkbox utiliza a função padrão do framework:

```javascript
$('#chkImagensAereas').on('change', function() {
    const visivel = $(this).is(':checked');
    MapFramework.alternarVisibilidadeCamada('imagens_aereas', visivel);
});
```

## 🐛 Debug

Para debug, observe o console:
- `📸 Imagens aéreas carregadas: X` - Quantidade de imagens encontradas
- `✅ X marcadores de imagens aéreas criados` - Marcadores criados com sucesso
- `⚠️ Imagem sem coordenadas` - Imagem sem lat/lng
- `❌ Erro ao carregar imagem` - Falha ao carregar a imagem

## 🎨 Personalização

### Mudar o ícone do marcador
Linha 2916 em `framework.js`:
```javascript
markerElement.innerHTML = '📷'; // Altere o emoji aqui
```

### Mudar o tamanho da imagem
Linha 3023 em `framework.js`:
```javascript
style="width: 500px; height: auto; ..." // Altere 500px
```

### Mudar a cor do título
Linha 2974 em `framework.js`:
```javascript
border-bottom: 2px solid #007bff; // Altere #007bff
```

## ✨ Recursos Extras

- **Spinner Bootstrap**: Loading visualmente agradável
- **Tooltip**: Hover mostra o nome da imagem
- **Posicionamento Inteligente**: InfoWindow abre na posição do marcador
- **Memory Management**: URL.createObjectURL para blobs
- **Responsivo**: maxWidth: 550px no InfoWindow
- **Cache**: Imagens cacheadas por 1 ano no navegador

## 🚀 Próximos Passos (Opcionais)

1. **Filtros**: Filtrar por data/altitude
2. **Miniaturas**: Grid de miniaturas
3. **Navegação**: Próxima/anterior imagem
4. **Download**: Botão para baixar a imagem
5. **Fullscreen**: Modal fullscreen para a imagem
6. **Metadados EXIF**: Exibir mais dados da foto

---

**Data de Implementação**: 08/10/2025  
**Desenvolvido por**: AI Assistant  
**Versão**: 1.0.0
