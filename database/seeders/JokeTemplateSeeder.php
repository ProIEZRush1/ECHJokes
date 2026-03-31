<?php

namespace Database\Seeders;

use App\Models\JokeTemplate;
use Illuminate\Database\Seeder;

class JokeTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $jokes = [
            // General
            ['category' => 'general', 'joke_text' => 'Oye, que le dijo un techo a otro techo? Techo de menos, compadre! Ya se que es malísimo pero ese nunca falla.'],
            ['category' => 'general', 'joke_text' => 'Fíjate que iba un pollito caminando por la calle y de repente se tropieza. Y que dice la mamá gallina: Ay mijo, pon atención! Y el pollito contestó: Dónde la pongo si no la conozco!'],
            ['category' => 'general', 'joke_text' => 'Sabes por qué el libro de matemáticas estaba triste? Porque tenía demasiados problemas. Pero el de historia estaba peor, porque no podía superar su pasado.'],
            ['category' => 'general', 'joke_text' => 'Entra un tipo a una biblioteca y dice: Me da una hamburguesa con queso! Y la bibliotecaria le dice: Señor, esto es una biblioteca. Y el tipo en voz bajita: Perdón... me da una hamburguesa con queso?'],
            ['category' => 'general', 'joke_text' => 'Doctor, doctor, tengo un problema: me siento caballo. Y el doctor le dice: Cuánto tiempo lleva así? Y el paciente: Desde potro.'],
            ['category' => 'general', 'joke_text' => 'Qué hace una abeja en el gimnasio? Zumba! Es malísimo pero te garantizo que te va a salir una sonrisa.'],
            ['category' => 'general', 'joke_text' => 'Le dice un jaguar a otro jaguar: Jaguar you? Y el otro le contesta: Pues aquí nomás, echando la hueva. Ese lo entiendes mejor si hablas spanglish.'],
            ['category' => 'general', 'joke_text' => 'Mamá, mamá, en la escuela me dicen distraído. Y la mamá le dice: Pepito, tú vives en la casa de al lado.'],
            ['category' => 'general', 'joke_text' => 'Qué le dijo la cuchara al tenedor? Qué onda, pinchudo! Y el tenedor le contestó: Pues aquí, muy cuchareable todo.'],
            ['category' => 'general', 'joke_text' => 'Un niño le pregunta a su papá: Papá, qué se siente tener un hijo tan guapo? Y el papá le dice: No sé, pregúntale a tu abuelo.'],

            // Dad jokes
            ['category' => 'dad', 'joke_text' => 'Hijo, te voy a contar un chiste de construcción... Espera, todavía lo estoy armando. Dame chance.'],
            ['category' => 'dad', 'joke_text' => 'Sabes qué le dijo el café al azúcar? Sin ti mi vida no tiene sentido. Pero con leche todo se pone mejor.'],
            ['category' => 'dad', 'joke_text' => 'Te cuento un chiste al revés? Empieza a reírte... Ahí va: Qué hace un pez en el agua? Nada. Ya puedes parar de reír.'],
            ['category' => 'dad', 'joke_text' => 'Cómo se despiden los químicos? Ácido un placer. Ese me lo enseñó mi maestro de secundaria y hasta la fecha me sigue dando risa.'],
            ['category' => 'dad', 'joke_text' => 'Papá, me prestas para un helado? Toma, pero no tomes frío. Bueno, papá, entonces me prestas para una torta? Toma, pero no tortas. Oye, me prestas para un refresco? Toma, pero no refresques el tema.'],
            ['category' => 'dad', 'joke_text' => 'Qué le dijo el 0 al 8? Bonito cinturón! Ese es de papá nivel tres, de los que te hacen rodar los ojos.'],
            ['category' => 'dad', 'joke_text' => 'Sabes por qué las focas del circo miran para arriba? Porque ahí están los focos! Sí, lo sé, horrible, pero así son los chistes de papá.'],
            ['category' => 'dad', 'joke_text' => 'Qué es un terapeuta? Un litro de cerveza. Ter-apeuta. Get it? Es de esos que necesitas pensarle un poquito.'],
            ['category' => 'dad', 'joke_text' => 'Cuál es el colmo de un electricista? Que su esposa se llame Luz y sus hijos le sigan la corriente.'],
            ['category' => 'dad', 'joke_text' => 'Oye papá, a qué temperatura hierve el agua? A cien grados. Ah qué fácil, y a qué temperatura se enfría? A cero grados. Y a qué temperatura se congela? En Celsius o en Fahrenheit? No papá, de la llave!'],

            // Dark humor
            ['category' => 'dark', 'joke_text' => 'Qué es lo mejor de Suiza? No sé, pero su bandera es un gran plus.'],
            ['category' => 'dark', 'joke_text' => 'Mi esposa me dijo que le falta emoción en su vida. Así que escondí su medicina. Ahorita está bien emocionada buscándola por toda la casa.'],
            ['category' => 'dark', 'joke_text' => 'Me dijeron que soy inmaduro. Les dije que se salieran de mi fuerte de almohadas. Nadie me falta al respeto en mi territorio.'],
            ['category' => 'dark', 'joke_text' => 'Qué le dice un gusano a otro gusano? Me voy a dar la vuelta a la manzana. Lo bueno es que no paga taxi.'],
            ['category' => 'dark', 'joke_text' => 'Por qué los esqueletos no pelean entre ellos? Porque no tienen agallas. Literalmente.'],

            // Adulto
            ['category' => 'adulto', 'joke_text' => 'Llega un hombre al doctor y le dice: Doctor, me duele aquí, aquí y aquí. Y el doctor le dice: Tiene el dedo roto, amigo.'],
            ['category' => 'adulto', 'joke_text' => 'Cómo se dice suegra en chino? No-la-quelo. Y cómo se dice nuera en chino? No-me-la-merezco.'],
            ['category' => 'adulto', 'joke_text' => 'Cuál es la diferencia entre un hombre y un calendario? El calendario tiene fechas.'],
            ['category' => 'adulto', 'joke_text' => 'Mi novia me dijo que necesitamos hablar. Le dije que también necesitamos comer pero eso no nos detiene de ir al cine.'],
            ['category' => 'adulto', 'joke_text' => 'Mira, te cuento uno: un hombre le dice a su esposa, cariño, qué harías si me gano la lotería? Y ella le dice: me quedaría con la mitad y me iría. Y él le dice: perfecto, me gané 20 pesos, ahí van tus 10, vete.'],

            // Political
            ['category' => 'political', 'joke_text' => 'En México hay dos tipos de personas: los que pagan impuestos y los que gobiernan. Y a veces ni eso segundo hacen bien.'],
            ['category' => 'political', 'joke_text' => 'Cuál es la diferencia entre un político y un mago? El mago te dice que te va a engañar y luego te sorprende. El político te dice que no te va a engañar y luego te sorprende igual.'],
            ['category' => 'political', 'joke_text' => 'Dicen que los políticos mexicanos son como las nubes. Cuando se van, el día mejora.'],
            ['category' => 'political', 'joke_text' => 'Saben por qué los políticos nunca juegan a las escondidas? Porque nadie los busca.'],
            ['category' => 'political', 'joke_text' => 'Un niño le dice a su papá: Papá, quiero ser político cuando sea grande. Y el papá le dice: Hijo, las dos cosas al mismo tiempo no se puede.'],
        ];

        foreach ($jokes as $joke) {
            JokeTemplate::firstOrCreate(
                ['joke_text' => $joke['joke_text']],
                $joke
            );
        }
    }
}
