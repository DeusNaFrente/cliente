/**
 * Máscaras para CPF e CNPJ
 * Fase 2: Suporte a PF e PJ
 */

/**
 * Valida CPF (11 dígitos com algoritmo módulo 11)
 * @param {string} cpf - CPF sem máscara (apenas números)
 * @returns {boolean}
 */
function validarCPF(cpf) {
    cpf = cpf.replace(/[^0-9]/g, "");
    if (cpf.length !== 11) return false;
    if (/^(\d)\1{10}$/.test(cpf)) return false;

    let soma = 0;
    for (let i = 0; i < 9; i++) {
        soma += parseInt(cpf[i]) * (10 - i);
    }
    let resto = soma % 11;
    let digito1 = resto < 2 ? 0 : 11 - resto;
    if (parseInt(cpf[9]) !== digito1) return false;

    soma = 0;
    for (let i = 0; i < 10; i++) {
        soma += parseInt(cpf[i]) * (11 - i);
    }
    resto = soma % 11;
    let digito2 = resto < 2 ? 0 : 11 - resto;
    if (parseInt(cpf[10]) !== digito2) return false;

    return true;
}

/**
 * Valida CNPJ (14 dígitos com algoritmo módulo 11)
 * @param {string} cnpj - CNPJ sem máscara (apenas números)
 * @returns {boolean}
 */
function validarCNPJ(cnpj) {
    cnpj = cnpj.replace(/[^0-9]/g, "");
    if (cnpj.length !== 14) return false;
    if (/^(\d)\1{13}$/.test(cnpj)) return false;

    const pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    const pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    let soma = 0;
    for (let i = 0; i < 12; i++) {
        soma += parseInt(cnpj[i]) * pesos1[i];
    }
    let resto = soma % 11;
    let digito1 = resto < 2 ? 0 : 11 - resto;
    if (parseInt(cnpj[12]) !== digito1) return false;

    soma = 0;
    for (let i = 0; i < 13; i++) {
        soma += parseInt(cnpj[i]) * pesos2[i];
    }
    resto = soma % 11;
    let digito2 = resto < 2 ? 0 : 11 - resto;
    if (parseInt(cnpj[13]) !== digito2) return false;

    return true;
}

/**
 * Mascara automática de CPF (999.999.999-99)
 * @param {HTMLElement} input - Input element
 */
function mascaraCPF(input) {
    let valor = input.value.replace(/[^0-9]/g, "");
    if (valor.length > 11) valor = valor.substring(0, 11);
    
    if (valor.length > 9) {
        valor = valor.substring(0, 3) + "." + valor.substring(3, 6) + "." + 
                valor.substring(6, 9) + "-" + valor.substring(9);
    } else if (valor.length > 6) {
        valor = valor.substring(0, 3) + "." + valor.substring(3, 6) + "." + 
                valor.substring(6);
    } else if (valor.length > 3) {
        valor = valor.substring(0, 3) + "." + valor.substring(3);
    }
    input.value = valor;
}

/**
 * Máscara automática de CNPJ (99.999.999/0000-99)
 * @param {HTMLElement} input - Input element
 */
function mascaraCNPJ(input) {
    let valor = input.value.replace(/[^0-9]/g, "");
    if (valor.length > 14) valor = valor.substring(0, 14);
    
    if (valor.length > 12) {
        valor = valor.substring(0, 2) + "." + valor.substring(2, 5) + "." + 
                valor.substring(5, 8) + "/" + valor.substring(8, 12) + "-" + 
                valor.substring(12);
    } else if (valor.length > 8) {
        valor = valor.substring(0, 2) + "." + valor.substring(2, 5) + "." + 
                valor.substring(5, 8) + "/" + valor.substring(8);
    } else if (valor.length > 5) {
        valor = valor.substring(0, 2) + "." + valor.substring(2, 5) + "." + 
                valor.substring(5);
    } else if (valor.length > 2) {
        valor = valor.substring(0, 2) + "." + valor.substring(2);
    }
    input.value = valor;
}

/**
 * Mascara automatica: formata como CPF enquanto tiver ate 11 digitos,
 * e passa a formatar como CNPJ a partir do 12o digito.
 * @param {HTMLElement} input - Input element
 */
function mascaraDocAuto(input) {
    let valor = input.value.replace(/[^0-9]/g, "");
    if (valor.length <= 11) {
        valor = valor.substring(0, 11);
        if (valor.length > 9) {
            valor = valor.substring(0, 3) + "." + valor.substring(3, 6) + "." +
                    valor.substring(6, 9) + "-" + valor.substring(9);
        } else if (valor.length > 6) {
            valor = valor.substring(0, 3) + "." + valor.substring(3, 6) + "." +
                    valor.substring(6);
        } else if (valor.length > 3) {
            valor = valor.substring(0, 3) + "." + valor.substring(3);
        }
    } else {
        valor = valor.substring(0, 14);
        if (valor.length > 12) {
            valor = valor.substring(0, 2) + "." + valor.substring(2, 5) + "." +
                    valor.substring(5, 8) + "/" + valor.substring(8, 12) + "-" +
                    valor.substring(12);
        } else if (valor.length > 8) {
            valor = valor.substring(0, 2) + "." + valor.substring(2, 5) + "." +
                    valor.substring(5, 8) + "/" + valor.substring(8);
        } else if (valor.length > 5) {
            valor = valor.substring(0, 2) + "." + valor.substring(2, 5) + "." +
                    valor.substring(5);
        } else if (valor.length > 2) {
            valor = valor.substring(0, 2) + "." + valor.substring(2);
        }
    }
    input.value = valor;
}

/**
 * Valida CPF ou CNPJ conforme a quantidade de digitos.
 * @param {string} doc
 * @returns {boolean}
 */
function validarDocAuto(doc) {
    doc = doc.replace(/[^0-9]/g, "");
    if (doc.length === 11) return validarCPF(doc);
    if (doc.length === 14) return validarCNPJ(doc);
    return false;
}
