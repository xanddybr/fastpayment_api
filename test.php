<?php

/**
 * Passo 1: O Contrato (Interface)
 * O bocal não precisa saber a marca da lâmpada, 
 * apenas que ela tem a capacidade de "acender".
 */
interface Iluminavel {
    public function acender();
    public function apagar();
}

/**
 * Passo 2: Implementações Reais
 */
class LampadaLED implements Iluminavel {
    public function acender() {
        return "Lâmpada LED ACESSA! com Brilho forte e econômico! 💡";
    }

    public function apagar() {
        return "Lâmpada LED APAGADA! com Brilho forte e econômico! 💡";
    }
}

class LampadaFluorescente implements Iluminavel {
    public function acender() {
        return "Lâmpada Fluorescente ACESSA! Piscando e aquecendo... 💡";

    }public function apagar() {
        return "Lâmpada Fluorescente APAGADA! Piscando e aquecendo... 💡";
    }
}

/**
 * Passo 3: O Bocal (A classe que recebe a Dependência)
 * Note que não damos "new" em nenhuma lâmpada aqui dentro.
 */
class Bocal {
    private $dispositivo;

    // Aqui acontece a Mágica: Injeção de Dependência via Construtor
    public function __construct(Iluminavel $lampada) {
        $this->dispositivo = $lampada;
    }

    public function ligarInterruptor() {
        echo "Acionando o interruptor...\n";
        echo $this->dispositivo->acender() . "\n";
    }

    public function desligarInterruptor() {
        echo "Desligando o interruptor...\n";
        echo $this->dispositivo->apagar() . "\n";
    }
}

/**
 * --- ÁREA DE TESTE ---
 */

echo "--- Teste 1: Com LED ---\n";

$led = new LampadaLED();
$bocalComLampada = new Bocal($led); // Injetando o objeto LED
$bocalComLampada->desligarInterruptor();