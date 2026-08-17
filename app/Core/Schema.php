<?php
namespace App\Core;

/**
 * Classe Schema
 *
 * Esta classe é usada para gerar esquemas JSON-LD compatíveis com a especificação schema.org.
 * Ela permite adicionar diferentes tipos de esquemas como FAQ, Produto, Avaliações, Curso,
 * Emprego, Organizações, Serviços, etc., com métodos flexíveis para parametrização.
 */
class Schema
{
    // Array que armazenará os dados do schema gerado
    private $data = [];

    // Array específico para entidades do tipo FAQ
    private $faqEntities = [];

    /**
     * Define o contexto e o tipo geral do schema.
     * O contexto é sempre "https://schema.org", e o tipo pode variar conforme o método chamado (e.g., "Product", "FAQPage").
     *
     * @param string $type Tipo do esquema a ser definido.
     */
    private function setContextAndType($type)
    {
        $this->data["@context"] = "https://schema.org";
        $this->data["@type"] = $type;
    }

    /**
     * Adiciona um esquema ao conjunto existente de dados.
     *
     * @param string $type Tipo do schema (e.g., "Product", "Review").
     * @param array $schema Array associativo com os dados do schema.
     */
    private function addSchema($type, $schema)
    {
        if (!isset($this->data[$type])) {
            $this->data[$type] = [];
        }
        $this->data[$type][] = $schema;
    }

    /**
     * Adiciona uma pergunta e resposta para um esquema de FAQPage.
     * As perguntas e respostas são armazenadas no array faqEntities, que posteriormente é adicionado ao mainEntity.
     *
     * @param string $question Pergunta.
     * @param string $answer Resposta correspondente à pergunta.
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addQuestion($question, $answer)
    {
        $this->faqEntities[] = [
            "@type" => "Question",
            "name" => $question,
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => $answer
            ]
        ];
        return $this;
    }

    /**
     * Cria um esquema para FAQPage com todas as perguntas e respostas adicionadas.
     * O método deve ser chamado após a adição das perguntas.
     *
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addFAQPage()
    {
        $this->setContextAndType("FAQPage");

        if (!empty($this->faqEntities)) {
            $this->data["mainEntity"] = $this->faqEntities;
        }

        return $this;
    }

    /**
     * Adiciona um esquema de produto. Todos os parâmetros são opcionais, e os valores são adicionados apenas se fornecidos.
     * O esquema inclui informações como nome, descrição, imagem, preço, condição do item, disponibilidade, etc.
     *
     * @param string|null $name Nome do produto.
     * @param string|null $description Descrição do produto.
     * @param string|null $image URL da imagem do produto.
     * @param string|null $sku Código SKU do produto.
     * @param string|null $brandName Nome da marca do produto.
     * @param float|null $price Preço do produto.
     * @param string $currency Moeda do preço (padrão: BRL).
     * @param string $condition Condição do produto (padrão: "NewCondition").
     * @param string $availability Disponibilidade do produto (padrão: "InStock").
     * @param string $url URL do produto.
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addProduct($name = null, $description = null, $image = null, $sku = null, $brandName = null, $price = null, $currency = "BRL", $condition = "NewCondition", $availability = "InStock", $url = "")
    {
        $productSchema = ["@type" => "Product"];
        if ($name) $productSchema["name"] = $name;
        if ($description) $productSchema["description"] = $description;
        if ($image) $productSchema["image"] = $image;
        if ($sku) $productSchema["sku"] = $sku;
        if ($brandName) {
            $productSchema["brand"] = [
                "@type" => "Brand",
                "name" => $brandName
            ];
        }
        if ($price) {
            $productSchema["offers"] = [
                "@type" => "Offer",
                "url" => $url,
                "priceCurrency" => $currency,
                "price" => $price,
                "itemCondition" => "https://schema.org/$condition",
                "availability" => "https://schema.org/$availability"
            ];
        }

        // Adiciona o esquema de Produto
        $this->addSchema("Product", $productSchema);
        return $this;
    }

    /**
     * Adiciona uma avaliação (review) ao esquema.
     * O método permite adicionar nome do autor, corpo da avaliação, valor da avaliação,
     * a classificação máxima e mínima, e o item avaliado.
     *
     * @param string|null $reviewerName Nome do autor da avaliação.
     * @param string|null $reviewBody Texto da avaliação.
     * @param int|null $ratingValue Valor da avaliação (nota).
     * @param int $bestRating Classificação máxima (padrão: 5).
     * @param int $worstRating Classificação mínima (padrão: 1).
     * @param array|null $itemReviewed Dados do item avaliado.
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addReview($reviewerName = null, $reviewBody = null, $ratingValue = null, $bestRating = "5", $worstRating = "1", $itemReviewed = null)
    {
        $reviewSchema = ["@type" => "Review"];
        if ($reviewerName) $reviewSchema["author"] = [
            "@type" => "Person",
            "name" => $reviewerName
        ];
        if ($reviewBody) $reviewSchema["reviewBody"] = $reviewBody;
        if ($ratingValue) $reviewSchema["reviewRating"] = [
            "@type" => "Rating",
            "ratingValue" => $ratingValue,
            "bestRating" => $bestRating,
            "worstRating" => $worstRating
        ];
        if ($itemReviewed) $reviewSchema["itemReviewed"] = $itemReviewed;

        // Adiciona o esquema de Avaliação
        $this->addSchema("Review", $reviewSchema);
        return $this;
    }

    /**
     * Método genérico para adicionar um curso. Permite fornecer dados opcionais como nome, descrição,
     * nome e URL do provedor, modo de curso, data de início e término.
     *
     * @param string|null $name Nome do curso.
     * @param string|null $description Descrição do curso.
     * @param string|null $providerName Nome do provedor do curso.
     * @param string|null $providerUrl URL do provedor.
     * @param string|null $courseMode Modo do curso (e.g., online, presencial).
     * @param string|null $startDate Data de início.
     * @param string|null $endDate Data de término.
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addCourse($name = null, $description = null, $providerName = null, $providerUrl = null, $courseMode = null, $startDate = null, $endDate = null)
    {
        $courseSchema = ["@type" => "Course"];
        if ($name) $courseSchema["name"] = $name;
        if ($description) $courseSchema["description"] = $description;
        if ($providerName) {
            $courseSchema["provider"] = [
                "@type" => "Organization",
                "name" => $providerName,
                "url" => $providerUrl
            ];
        }
        if ($courseMode) $courseSchema["courseMode"] = $courseMode;
        if ($startDate) $courseSchema["startDate"] = $startDate;
        if ($endDate) $courseSchema["endDate"] = $endDate;

        // Adiciona o esquema de Curso
        $this->addSchema("Course", $courseSchema);
        return $this;
    }

    /**
     * Adiciona um esquema de postagem de vaga de emprego (JobPosting) com parâmetros opcionais.
     * Inclui informações como título do emprego, descrição, data de postagem, organização contratante, localização e salário.
     *
     * @param string|null $title Título da vaga de emprego.
     * @param string|null $description Descrição da vaga.
     * @param string|null $datePosted Data de postagem da vaga.
     * @param string|null $hiringOrganization Nome da organização contratante.
     * @param string|null $jobLocation Localização do emprego.
     * @param float|null $salary Salário base oferecido.
     * @param string|null $employmentType Tipo de emprego (ex: Full-time, Part-time).
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addJobPosting($title = null, $description = null, $datePosted = null, $hiringOrganization = null, $jobLocation = null, $salary = null, $employmentType = null)
    {
        $jobPostingSchema = ["@type" => "JobPosting"];
        if ($title) $jobPostingSchema["title"] = $title;
        if ($description) $jobPostingSchema["description"] = $description;
        if ($datePosted) $jobPostingSchema["datePosted"] = $datePosted;
        if ($hiringOrganization) $jobPostingSchema["hiringOrganization"] = [
            "@type" => "Organization",
            "name" => $hiringOrganization
        ];
        if ($jobLocation) $jobPostingSchema["jobLocation"] = [
            "@type" => "Place",
            "address" => [
                "@type" => "PostalAddress",
                "addressLocality" => $jobLocation
            ]
        ];
        if ($salary) $jobPostingSchema["baseSalary"] = $salary;
        if ($employmentType) $jobPostingSchema["employmentType"] = $employmentType;

        // Adiciona o esquema de Vaga de Emprego
        $this->addSchema("JobPosting", $jobPostingSchema);
        return $this;
    }

    /**
     * Adiciona um esquema de Organização (Organization) com parâmetros opcionais.
     * Inclui informações como nome da organização, URL, logotipo e informações de contato.
     *
     * @param string|null $name Nome da organização.
     * @param string|null $url URL do site da organização.
     * @param string|null $logo URL do logotipo da organização.
     * @param array $contactPoint Informações de contato, como tipo de contato e email.
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addOrganization($name = null, $url = null, $logo = null, $contactPoint = [])
    {
        $organizationSchema = ["@type" => "Organization"];
        if ($name) $organizationSchema["name"] = $name;
        if ($url) $organizationSchema["url"] = $url;
        if ($logo) $organizationSchema["logo"] = $logo;
        if (!empty($contactPoint)) $organizationSchema["contactPoint"] = array_map(function ($item) {
            return [
                "@type" => "ContactPoint",
                "contactType" => $item['contactType'],
                "email" => $item['email']
            ];
        }, $contactPoint);

        // Adiciona o esquema de Organização
        $this->addSchema("Organization", $organizationSchema);
        return $this;
    }

    /**
     * Adiciona um esquema de Vídeo (VideoObject) com parâmetros opcionais.
     * Inclui informações como nome, descrição, URL do thumbnail, data de upload e duração.
     *
     * @param string|null $name Nome do vídeo.
     * @param string|null $description Descrição do vídeo.
     * @param string|null $thumbnailUrl URL da imagem em miniatura do vídeo.
     * @param string|null $uploadDate Data de upload do vídeo.
     * @param string|null $duration Duração do vídeo.
     * @param string|null $contentUrl URL do conteúdo do vídeo.
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addVideoObject($name = null, $description = null, $thumbnailUrl = null, $uploadDate = null, $duration = null, $contentUrl = null)
    {
        $videoObjectSchema = ["@type" => "VideoObject"];
        if ($name) $videoObjectSchema["name"] = $name;
        if ($description) $videoObjectSchema["description"] = $description;
        if ($thumbnailUrl) $videoObjectSchema["thumbnailUrl"] = $thumbnailUrl;
        if ($uploadDate) $videoObjectSchema["uploadDate"] = $uploadDate;
        if ($duration) $videoObjectSchema["duration"] = $duration;
        if ($contentUrl) $videoObjectSchema["contentUrl"] = $contentUrl;

        // Adiciona o esquema de Vídeo
        $this->addSchema("VideoObject", $videoObjectSchema);
        return $this;
    }

    /**
     * Adiciona um esquema de Serviço (Service) com parâmetros opcionais.
     * Inclui informações como tipo de serviço, nome do provedor, URL do provedor, área de serviço e descrição.
     *
     * @param string|null $serviceType Tipo de serviço.
     * @param string|null $providerName Nome do provedor do serviço.
     * @param string|null $providerUrl URL do provedor do serviço.
     * @param string|null $serviceArea Área onde o serviço é prestado.
     * @param string|null $description Descrição do serviço.
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addService($serviceType = null, $providerName = null, $providerUrl = null, $serviceArea = null, $description = null)
    {
        $serviceSchema = ["@type" => "Service"];
        if ($serviceType) $serviceSchema["serviceType"] = $serviceType;
        if ($providerName) $serviceSchema["provider"] = [
            "@type" => "Organization",
            "name" => $providerName,
            "url" => $providerUrl
        ];
        if ($serviceArea) $serviceSchema["serviceArea"] = [
            "@type" => "Place",
            "address" => [
                "@type" => "PostalAddress",
                "addressLocality" => $serviceArea
            ]
        ];
        if ($description) $serviceSchema["description"] = $description;

        // Adiciona o esquema de Serviço
        $this->addSchema("Service", $serviceSchema);
        return $this;
    }

    /**
     * Adiciona um esquema de Atração Turística (TouristAttraction) com parâmetros opcionais.
     * Inclui informações como nome, descrição, imagem, endereço e coordenadas geográficas.
     *
     * @param string|null $name Nome da atração turística.
     * @param string|null $description Descrição da atração turística.
     * @param string|null $image URL da imagem da atração.
     * @param string|null $address Endereço da atração.
     * @param array|null $geo Coordenadas geográficas (latitude e longitude).
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addTouristAttraction($name = null, $description = null, $image = null, $address = null, $geo = null)
    {
        $touristAttractionSchema = ["@type" => "TouristAttraction"];
        if ($name) $touristAttractionSchema["name"] = $name;
        if ($description) $touristAttractionSchema["description"] = $description;
        if ($image) $touristAttractionSchema["image"] = $image;
        if ($address) $touristAttractionSchema["address"] = [
            "@type" => "PostalAddress",
            "addressLocality" => $address
        ];
        if ($geo) $touristAttractionSchema["geo"] = $geo;

        // Adiciona o esquema de Atração Turística
        $this->addSchema("TouristAttraction", $touristAttractionSchema);
        return $this;
    }

     /**
     * Adiciona um esquema de Livro (Book) com parâmetros opcionais.
     * Inclui informações como nome, autor, ISBN, editora, data de publicação e descrição.
     *
     * @param string|null $name Nome do livro.
     * @param string|null $author Nome do autor.
     * @param string|null $isbn Código ISBN do livro.
     * @param string|null $publisher Nome da editora.
     * @param string|null $datePublished Data de publicação do livro.
     * @param string|null $description Descrição do livro.
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addBook($name = null, $author = null, $isbn = null, $publisher = null, $datePublished = null, $description = null)
    {
        $bookSchema = ["@type" => "Book"];
        if ($name) $bookSchema["name"] = $name;
        if ($author) $bookSchema["author"] = [
            "@type" => "Person",
            "name" => $author
        ];
        if ($isbn) $bookSchema["isbn"] = $isbn;
        if ($publisher) $bookSchema["publisher"] = [
            "@type" => "Organization",
            "name" => $publisher
        ];
        if ($datePublished) $bookSchema["datePublished"] = $datePublished;
        if ($description) $bookSchema["description"] = $description;

        // Adiciona o esquema de Livro
        $this->addSchema("Book", $bookSchema);
        return $this;
    }

    /**
     * Adiciona um esquema de Artigo (Article) com parâmetros opcionais.
     * Inclui informações como manchete, autor, data de publicação, conteúdo e imagem.
     *
     * @param string|null $headline Manchete do artigo.
     * @param string|null $author Nome do autor.
     * @param string|null $datePublished Data de publicação do artigo.
     * @param string|null $articleBody Conteúdo do artigo.
     * @param string|null $image URL da imagem associada ao artigo.
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addArticle($headline = null, $author = null, $datePublished = null, $articleBody = null, $image = null)
    {
        $articleSchema = ["@type" => "Article"];
        if ($headline) $articleSchema["headline"] = $headline;
        if ($author) $articleSchema["author"] = [
            "@type" => "Person",
            "name" => $author
        ];
        if ($datePublished) $articleSchema["datePublished"] = $datePublished;
        if ($articleBody) $articleSchema["articleBody"] = $articleBody;
        if ($image) $articleSchema["image"] = $image;

        // Adiciona o esquema de Artigo
        $this->addSchema("Article", $articleSchema);
        return $this;
    }

    /**
     * Adiciona um esquema de Negócio Local (LocalBusiness) com parâmetros opcionais.
     * Inclui informações como nome, endereço, telefone, horário de funcionamento e coordenadas geográficas.
     *
     * @param string|null $name Nome do negócio local.
     * @param string|null $address Endereço do negócio.
     * @param string|null $phone Telefone de contato do negócio.
     * @param string|null $openingHours Horário de funcionamento do negócio.
     * @param array|null $geo Coordenadas geográficas (latitude e longitude).
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addLocalBusiness($name = null, $address = null, $phone = null, $openingHours = null, $geo = null)
    {
        $localBusinessSchema = ["@type" => "LocalBusiness"];
        if ($name) $localBusinessSchema["name"] = $name;
        if ($address) $localBusinessSchema["address"] = [
            "@type" => "PostalAddress",
            "addressLocality" => $address
        ];
        if ($phone) $localBusinessSchema["telephone"] = $phone;
        if ($openingHours) $localBusinessSchema["openingHours"] = $openingHours;
        if ($geo) $localBusinessSchema["geo"] = $geo;
        $this->addSchema("LocalBusiness", $localBusinessSchema);
        return $this;
    }

    /**
     * Adiciona um esquema de Evento (Event) com parâmetros opcionais.
     * Inclui informações como nome, data de início, data de término, localização e descrição.
     *
     * @param string|null $name Nome do evento.
     * @param string|null $startDate Data de início do evento.
     * @param string|null $endDate Data de término do evento.
     * @param string|null $location Localização do evento.
     * @param string|null $description Descrição do evento.
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addEvent($name = null, $startDate = null, $endDate = null, $location = null, $description = null)
    {
        $eventSchema = ["@type" => "Event"];
        if ($name) $eventSchema["name"] = $name;
        if ($startDate) $eventSchema["startDate"] = $startDate;
        if ($endDate) $eventSchema["endDate"] = $endDate;
        if ($location) $eventSchema["location"] = [
            "@type" => "Place",
            "name" => $location
        ];
        if ($description) $eventSchema["description"] = $description;

        // Adiciona o esquema de Negócio Local
        $this->addSchema("Event", $eventSchema);
        return $this;
    }

    /**
     * Adiciona um esquema de Pessoa (Person) com parâmetros opcionais.
     * Inclui informações como nome, título do cargo, telefone, endereço e email.
     *
     * @param string|null $name Nome da pessoa.
     * @param string|null $jobTitle Título do cargo da pessoa.
     * @param string|null $telephone Telefone da pessoa.
     * @param string|null $address Endereço da pessoa.
     * @param string|null $email Email da pessoa.
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addPerson($name = null, $jobTitle = null, $telephone = null, $address = null, $email = null)
    {
        $personSchema = ["@type" => "Person"];
        if ($name) $personSchema["name"] = $name;
        if ($jobTitle) $personSchema["jobTitle"] = $jobTitle;
        if ($telephone) $personSchema["telephone"] = $telephone;
        if ($address) $personSchema["address"] = [
            "@type" => "PostalAddress",
            "addressLocality" => $address
        ];
        if ($email) $personSchema["email"] = $email;

        // Adiciona o esquema de Pessoa
        $this->addSchema("Person", $personSchema);
        return $this;
    }


    /**
     * Adiciona um esquema de Receita (Recipe) com parâmetros opcionais.
     * Inclui informações como nome, ingredientes, instruções, tempo de preparação, tempo de cozimento, rendimento da receita e descrição.
     *
     * @param string|null $name Nome da receita.
     * @param array $recipeIngredient Lista de ingredientes da receita.
     * @param string|null $recipeInstructions Instruções de preparo da receita.
     * @param string|null $prepTime Tempo de preparação da receita.
     * @param string|null $cookTime Tempo de cozimento da receita.
     * @param string|null $totalTime Tempo total para preparar e cozinhar a receita.
     * @param string|null $recipeYield Rendimento da receita.
     * @param string|null $description Descrição da receita.
     * @return $this Retorna a instância da classe para permitir encadeamento.
     */
    public function addRecipe($name = null, $recipeIngredient = [], $recipeInstructions = null, $prepTime = null, $cookTime = null, $totalTime = null, $recipeYield = null, $description = null)
    {
        $recipeSchema = ["@type" => "Recipe"];
        if ($name) $recipeSchema["name"] = $name;
        if (!empty($recipeIngredient)) $recipeSchema["recipeIngredient"] = $recipeIngredient;
        if ($recipeInstructions) $recipeSchema["recipeInstructions"] = $recipeInstructions;
        if ($prepTime) $recipeSchema["prepTime"] = $prepTime;
        if ($cookTime) $recipeSchema["cookTime"] = $cookTime;
        if ($totalTime) $recipeSchema["totalTime"] = $totalTime;
        if ($recipeYield) $recipeSchema["recipeYield"] = $recipeYield;
        if ($description) $recipeSchema["description"] = $description;

        // Adiciona o esquema de Receita
        $this->addSchema("Recipe", $recipeSchema);
        return $this;
    }


    /**
     * Gera o JSON-LD no formato especificado.
     * O método pode gerar o JSON diretamente ou dentro de uma tag <script> para ser embutido em HTML.
     *
     * @param string $output Especifica o formato de saída. Pode ser 'json' para JSON puro ou 'script' para incluir o JSON em uma tag <script>.
     * @return string Retorna o JSON-LD gerado ou a tag <script> com o JSON-LD.
     */
    public function generate($output = "json")
    {
        // Converte os dados para JSON com formatação legível e sem escapar barras
        $jsonLd = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($output == "json") {
            // Retorna JSON puro se 'json' for especificado
            return $jsonLd;
        } elseif ($output == "script") {
            // Retorna a tag <script> com JSON-LD se 'script' for especificado
            return "<script type=\"application/ld+json\">\n" . $jsonLd . "\n</script>";
        }
    }
}

/*********
* Exemplos de uso:

// Produto e Avaliações
$schema = new Schema();
echo $schema->addProduct(
    "Tênis de Corrida",
    "Tênis ideal para corridas de longa distância.",
    "https://exemplo.com/tenis.jpg",
    "12345",
    "Marca Exemplo",
    "299.90",
    "BRL",
    "NewCondition",
    "InStock",
    "https://exemplo.com/tenis"
)->addReview(
    "João Silva",
    "Ótimo produto! Super confortável.",
    "5"
)->addReview(
    "Ana Souza",
    "Bom, mas poderia ser mais barato.",
    "4"
)->generate();

// Página de FAQ com Perguntas
$schema = new Schema();
$schema->addQuestion("Qual é o horário de atendimento?", "Das 9h às 18h de segunda a sexta.")
       ->addQuestion("Onde posso encontrar o suporte?", "Em nosso site, na seção de suporte.")
       ->addFAQPage()
       ->generate();

// Curso
$schema = new Schema();
echo $schema->addCourse(
    "Desenvolvimento Web",
    "Curso completo de desenvolvimento web.",
    "Universidade Exemplo",
    "https://universidadeexemplo.com",
    "Online",
    "2024-01-15",
    "2024-12-15"
)->generate();

// Postagem de emprego
$schema = new Schema();
echo $schema->addJobPosting(
    "Desenvolvedor PHP",
    "Procuramos um desenvolvedor PHP experiente.",
    "2024-09-01",
    "Empresa Exemplo",
    "São Paulo",
    "R$ 6000 - R$ 8000",
    "FullTime"
)->generate();

// Organização
$schema = new Schema();
echo $schema->addOrganization(
    "Empresa Exemplo",
    "https://empresaexemplo.com",
    "https://empresaexemplo.com/logo.png",
    [
        ["contactType" => "Suporte", "email" => "suporte@empresaexemplo.com"]
    ]
)->generate();

// Objeto de vídeo
$schema = new Schema();
echo $schema->addVideoObject(
    "Tutorial PHP",
    "Vídeo tutorial sobre PHP.",
    "https://exemplo.com/thumb.jpg",
    "2024-09-01",
    "PT1H30M",
    "https://exemplo.com/video.mp4"
)->generate();

// Serviço
$schema = new Schema();
echo $schema->addService(
    "Desenvolvimento de Sites",
    "Desenvolvemos sites personalizados.",
    "https://empresaexemplo.com",
    "São Paulo",
    "Serviço de criação e manutenção de sites."
)->generate();

// Atração turística
$schema = new Schema();
echo $schema->addTouristAttraction(
    "Cristo Redentor",
    "Famosa estátua no Rio de Janeiro.",
    "https://exemplo.com/cristo.jpg",
    "Rio de Janeiro",
    [
        "@type" => "GeoCoordinates",
        "latitude" => "-22.9519",
        "longitude" => "-43.2105"
    ]
)->generate();

// Livro
$schema = new Schema();
echo $schema->addBook(
    "O Pequeno Príncipe",
    "Antoine de Saint-Exupéry",
    "978-8533611640",
    "Editora Exemplo",
    "1943-04-06",
    "Um conto sobre a importância de enxergar além das aparências."
)->generate();

// Artigo
$schema = new Schema();
echo $schema->addArticle(
    "Como aprender PHP",
    "Maria Oliveira",
    "2024-09-01",
    "Neste artigo, vamos explorar como aprender PHP de maneira eficaz.",
    "https://exemplo.com/php.jpg"
)->generate();

// Negócio local
$schema = new Schema();
echo $schema->addLocalBusiness(
    "Restaurante Exemplo",
    "Rua das Flores, 123",
    "+55 11 99999-9999",
    "Seg-Sex 11h-23h, Sáb-Dom 12h-22h",
    [
        "@type" => "GeoCoordinates",
        "latitude" => "-23.5505",
        "longitude" => "-46.6333"
    ]
)->generate();

// Evento
$schema = new Schema();
echo $schema->addEvent(
    "Festival de Música",
    "2024-10-01T18:00:00",
    "2024-10-03T23:00:00",
    "Parque das Nações",
    "Um festival com várias atrações musicais."
)->generate();

// Pessoa
$schema = new Schema();
echo $schema->addPerson(
    "Carlos Silva",
    "Desenvolvedor Web",
    "+55 11 98765-4321",
    "São Paulo",
    "carlos@exemplo.com"
)->generate();

// Receita
$schema = new Schema();
echo $schema->addRecipe(
    "Bolo de Chocolate",
    ["Farinha", "Cacau em pó", "Açúcar", "Ovos"],
    "Misture todos os ingredientes e asse por 30 minutos.",
    "PT20M",
    "PT30M",
    "PT50M",
    "8 porções",
    "Uma receita deliciosa de bolo de chocolate."
)->generate();

*********/
